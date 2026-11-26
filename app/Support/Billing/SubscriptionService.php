<?php

namespace App\Support\Billing;

use App\Enums\BillingInterval;
use App\Enums\SubscriptionStatus;
use App\Exceptions\InvalidSubscriptionTransition;
use App\Exceptions\SubscriptionAlreadyRunning;
use App\Exceptions\UnknownPlan;
use App\Models\Subscription;
use App\Models\Team;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;

/**
 * Every write to a subscription goes through here.
 *
 * Why a service instead of model methods: a status change is never just a column.
 * Paying starts a period, canceling decides whether access ends now or at period
 * close, and every move has to obey {@see SubscriptionStatus}. Spreading that over
 * controllers, jobs and webhook handlers is how a subscription ends up `active`
 * without a paid period — the exact failure the billing workstream exists to avoid
 * (RSK-02, TD5-5).
 *
 * The provider never calls this class: Midtrans is not a subscription engine (D-05),
 * it only reports the result of one transaction. Mapping a webhook to an invoice to
 * one of these calls is tugas 24 Nov.
 *
 * Times are injectable so jobs and tests can state the moment instead of relying on
 * `now()` inside the method.
 *
 * @see \docs\implementation\phase-04-plan-dan-entitlement.md §4.5
 */
final class SubscriptionService
{
    public function __construct(private readonly InvoiceService $invoices) {}

    /**
     * Start a tenant's trial, by default the 30-day Basic trial of §B.4.3.
     *
     * The trial deliberately has no `billing_interval`: nothing is being billed
     * yet, and the first payment decides whether the tenant buys a month or a year.
     *
     * @throws SubscriptionAlreadyRunning when the tenant already has a running subscription
     * @throws UnknownPlan when the requested plan is missing from config
     */
    public function startTrial(
        Team $team,
        ?string $planCode = null,
        ?CarbonImmutable $startedAt = null,
    ): Subscription {
        $planCode ??= (string) config('plans.default_plan');

        /** @var array<string, array{name: string}> $plans */
        $plans = config('plans.plans');

        if (! array_key_exists($planCode, $plans)) {
            throw UnknownPlan::named($planCode);
        }

        return app(TenantContext::class)->runFor($team, function (Team $team) use ($planCode, $startedAt): Subscription {
            if ($team->currentSubscription()->exists()) {
                throw SubscriptionAlreadyRunning::for($team);
            }

            $start = $startedAt ?? CarbonImmutable::now();
            $trialEnd = $start->addDays((int) config('billing.trial_days', 30));

            return $team->subscriptions()->create([
                'plan_code' => $planCode,
                'billing_interval' => null,
                'status' => SubscriptionStatus::Trialing,
                'trial_ends_at' => $trialEnd,
                'current_period_start' => $start,
                'current_period_end' => $trialEnd,
                'canceled_at' => null,
                'provider' => (string) config('billing.provider', 'midtrans'),
                'provider_reference' => null,
                'ended_at' => null,
            ]);
        });
    }

    /**
     * Record a successful payment and make the subscription paid and current.
     *
     * The new period starts at the end of the period already paid for (or at the
     * end of the trial), so a tenant paying early does not lose days; when the old
     * period is already over, the new one starts at the payment. Midtrans has no
     * proration (D-05), so days are never partly charged.
     *
     * @throws InvalidSubscriptionTransition unless the subscription is `trialing` or `past_due`
     */
    public function activate(
        Subscription $subscription,
        BillingInterval $interval,
        ?string $providerReference = null,
        ?CarbonImmutable $paidAt = null,
        ?CarbonImmutable $periodStart = null,
    ): Subscription {
        $this->assertCanTransitionTo($subscription, SubscriptionStatus::Active);

        $payment = $paidAt ?? CarbonImmutable::now();
        $paidUntil = $subscription->current_period_end;

        // A webhook knows the period the invoice was for; that period wins, because
        // paying nine days late still buys the billed month, not a fresh one.
        $start = $periodStart ?? ($paidUntil->greaterThan($payment) ? $paidUntil : $payment);

        $subscription->status = SubscriptionStatus::Active;
        $subscription->billing_interval = $interval;
        $subscription->current_period_start = $start;
        $subscription->current_period_end = $interval->advance($start);

        if ($providerReference !== null) {
            $subscription->provider_reference = $providerReference;
        }

        $subscription->save();

        // Every paid period gets a matching invoice, so dunning and the 26 Nov
        // "Paket saya" page have a complete history instead of starting blank.
        $this->invoices->recordPaidPeriod($subscription, $payment, $providerReference);

        return $subscription;
    }

    /**
     * The period's invoice was not paid: start the grace period of §B.4.3.
     *
     * Access stays on while `past_due` (`SubscriptionStatus::grantsAccess()`), which
     * is what gives dunning its seven days.
     *
     * @throws InvalidSubscriptionTransition unless the subscription is `active`
     */
    public function markPastDue(Subscription $subscription): Subscription
    {
        $this->assertCanTransitionTo($subscription, SubscriptionStatus::PastDue);

        $subscription->status = SubscriptionStatus::PastDue;
        $subscription->save();

        return $subscription;
    }

    /**
     * The grace period ran out: end the subscription.
     *
     * @throws InvalidSubscriptionTransition unless the subscription is `past_due` or `trialing`
     */
    public function markExpired(Subscription $subscription, ?CarbonImmutable $at = null): Subscription
    {
        $this->assertCanTransitionTo($subscription, SubscriptionStatus::Expired);

        $subscription->status = SubscriptionStatus::Expired;
        $subscription->ended_at = $at ?? CarbonImmutable::now();
        $subscription->save();

        return $subscription;
    }

    /**
     * Cancel the subscription.
     *
     * A trial ends immediately — there is no paid period to serve out. A paid
     * subscription keeps running until `current_period_end` and simply stops
     * renewing (B.4.3: cancellation takes effect at the end of the period); the
     * period-closing job then calls {@see markCanceled()}. `canceled_at` is the
     * moment the tenant asked, and a second request does not move it.
     *
     * @throws InvalidSubscriptionTransition when the subscription is already final
     */
    public function cancel(Subscription $subscription, ?CarbonImmutable $at = null): Subscription
    {
        if ($subscription->canceled_at !== null) {
            return $subscription;
        }

        $requestedAt = $at ?? CarbonImmutable::now();

        $this->assertCanTransitionTo($subscription, SubscriptionStatus::Canceled);

        $subscription->canceled_at = $requestedAt;

        if ($subscription->status === SubscriptionStatus::Trialing) {
            $subscription->status = SubscriptionStatus::Canceled;
            $subscription->ended_at = $requestedAt;
        }

        $subscription->save();

        return $subscription;
    }

    /**
     * End a subscription at period close, honoring a cancellation request.
     *
     * Called by the period-check job (tugas 19 Nov) — never by a webhook, because
     * the provider only reports transactions (D-05).
     *
     * @throws InvalidSubscriptionTransition unless the subscription is `active` or `past_due`
     */
    public function markCanceled(Subscription $subscription, ?CarbonImmutable $at = null): Subscription
    {
        $this->assertCanTransitionTo($subscription, SubscriptionStatus::Canceled);

        $subscription->status = SubscriptionStatus::Canceled;
        $subscription->ended_at = $at ?? CarbonImmutable::now();
        $subscription->save();

        return $subscription;
    }

    /**
     * @throws InvalidSubscriptionTransition
     */
    private function assertCanTransitionTo(Subscription $subscription, SubscriptionStatus $status): void
    {
        if (! $subscription->canTransitionTo($status)) {
            throw InvalidSubscriptionTransition::between($subscription->status, $status);
        }
    }
}
