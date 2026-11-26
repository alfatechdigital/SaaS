<?php

namespace App\Support\Billing;

use App\Enums\BillingInterval;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use LogicException;

/**
 * The only writer of `invoices`.
 *
 * Two rules keep billing honest:
 *
 * 1. **One invoice per period.** The period start identifies the invoice, and a
 *    unique index backs that up, so a job that runs twice or a retried webhook
 *    cannot bill the same month again (RSK-02).
 * 2. **A period is billed from its start.** The renewal invoice is issued for the
 *    period that begins when the paid one ends, and its amount comes from
 *    `config/billing.php` at issue time — a later price change never rewrites an
 *    invoice that was already sent.
 *
 * Amounts never come from the provider: Midtrans only charges what it is told
 * (D-05).
 *
 * Queries run inside the subscription's tenant context, because `invoices` is
 * tenant-owned and the job/command that calls this may have no request tenant.
 */
final class InvoiceService
{
    /**
     * Issue — or return — the open invoice for the period after the current one.
     *
     * Used when a period runs out: the tenant keeps the service in the 7-day
     * grace period while this invoice is chased.
     */
    public function issueNext(Subscription $subscription): Invoice
    {
        return app(TenantContext::class)->runFor($subscription->team, function () use ($subscription): Invoice {
            $interval = $subscription->billing_interval ?? BillingInterval::Monthly;

            return $this->forPeriod($subscription, $subscription->current_period_end, $interval);
        });
    }

    /**
     * Record the period the subscription has just entered as paid.
     *
     * Called by {@see SubscriptionService::activate()}, so every paid period has a
     * matching invoice. Any open invoice for an *earlier* period is voided first:
     * it was the bill for a period a late payment has now replaced (a new period
     * starts at payment when the old one already ended), and leaving both open
     * would be a double bill.
     */
    public function recordPaidPeriod(
        Subscription $subscription,
        ?CarbonImmutable $paidAt = null,
        ?string $providerReference = null,
    ): Invoice {
        return app(TenantContext::class)->runFor($subscription->team, function () use ($subscription, $paidAt, $providerReference): Invoice {
            $interval = $subscription->billing_interval ?? BillingInterval::Monthly;
            $start = $subscription->current_period_start;

            $subscription->invoices()
                ->where('status', InvoiceStatus::Open->value)
                ->where('period_start', '<', $start)
                ->update(['status' => InvoiceStatus::Void->value, 'updated_at' => CarbonImmutable::now()]);

            return $this->markPaid(
                $this->forPeriod($subscription, $start, $interval),
                $paidAt ?? CarbonImmutable::now(),
                $providerReference,
            );
        });
    }

    /**
     * Settle an invoice.
     *
     * Idempotent on purpose: a payment webhook may be delivered twice (tugas 24
     * Nov), and the second delivery must not move `paid_at` or un-pay anything.
     */
    public function markPaid(
        Invoice $invoice,
        ?CarbonImmutable $paidAt = null,
        ?string $providerReference = null,
    ): Invoice {
        if ($invoice->isPaid()) {
            return $invoice;
        }

        $invoice->status = InvoiceStatus::Paid;
        $invoice->paid_at = $paidAt ?? CarbonImmutable::now();

        if ($providerReference !== null) {
            $invoice->provider_reference = $providerReference;
        }

        $invoice->save();

        return $invoice;
    }

    /**
     * The invoice for one period, created on first use.
     */
    private function forPeriod(
        Subscription $subscription,
        CarbonImmutable $periodStart,
        BillingInterval $interval,
    ): Invoice {
        $existing = $subscription->invoices()
            ->where('period_start', $periodStart)
            ->first();

        if ($existing instanceof Invoice) {
            return $existing;
        }

        return $subscription->invoices()->create([
            // `team_id` is denormalised onto the row so tenant queries do not need
            // a join; the relation fills `subscription_id` itself.
            'team_id' => $subscription->team_id,
            'number' => $this->number($subscription, $periodStart),
            'status' => InvoiceStatus::Open,
            'billing_interval' => $interval,
            'amount' => $this->amountFor($subscription->plan_code, $interval),
            'currency' => 'IDR',
            'period_start' => $periodStart,
            'period_end' => $interval->advance($periodStart),
            'due_at' => $periodStart,
            'paid_at' => null,
            'reminders_sent' => 0,
            'reminded_at' => null,
            'provider' => $subscription->provider,
            'provider_reference' => null,
        ]);
    }

    /**
     * A deterministic number: the same period can never produce two numbers, which
     * also makes the unique index on `number` meaningful.
     */
    private function number(Subscription $subscription, CarbonImmutable $periodStart): string
    {
        return sprintf('INV-%s-%d', $periodStart->format('Ymd'), (int) $subscription->getKey());
    }

    private function amountFor(string $planCode, BillingInterval $interval): int
    {
        $key = $interval === BillingInterval::Yearly ? 'price_yearly' : 'price_monthly';

        $amount = config("billing.plans.{$planCode}.{$key}");

        if (! is_int($amount)) {
            throw new LogicException(sprintf(
                'config/billing.php has no %s for plan [%s]; an invoice cannot be issued without a price.',
                $key,
                $planCode,
            ));
        }

        return $amount;
    }
}
