<?php

namespace App\Support\Billing;

use App\Enums\SubscriptionStatus;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;

/**
 * Closes periods that have run out, once a day (B-2, tugas 19 Nov).
 *
 * Midtrans only reports one transaction at a time (D-05), so nothing else will
 * tell us a subscription is due. This is the missing clock, and it is deliberately
 * the only place where "time" decides a status:
 *
 * - **Canceled at period end** → finished, no further invoice.
 * - **Trial over** → expired; the trial was never a bill.
 * - **Paid period over** → issue the renewal invoice and enter `past_due`, so the
 *   tenant keeps access while the 7-day grace period runs.
 * - **Grace over** → expired.
 * - **Otherwise** → send the next dunning reminder, if its day has arrived.
 *
 * The checker opens the tenant context itself, because it queries tenant-owned
 * invoices and may be called from a console command with no request in flight.
 */
final class SubscriptionPeriodChecker
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly InvoiceService $invoices,
        private readonly InvoiceDunning $dunning,
    ) {}

    public function check(Subscription $subscription, ?CarbonImmutable $now = null): void
    {
        $at = $now ?? CarbonImmutable::now();

        app(TenantContext::class)->runFor($subscription->team, function () use ($subscription, $at): void {
            $this->checkInContext($subscription, $at);
        });
    }

    private function checkInContext(Subscription $subscription, CarbonImmutable $now): void
    {
        if (! $subscription->isCurrent() || $subscription->current_period_end->greaterThan($now)) {
            return;
        }

        if ($subscription->canceled_at !== null) {
            $this->subscriptions->markCanceled($subscription, $now);

            return;
        }

        if ($subscription->status === SubscriptionStatus::Trialing) {
            $this->subscriptions->markExpired($subscription, $now);

            return;
        }

        $invoice = $subscription->invoices()->open()->latest('period_start')->first();

        if ($subscription->status === SubscriptionStatus::Active) {
            $invoice ??= $this->invoices->issueNext($subscription);
            $this->subscriptions->markPastDue($subscription);
        } elseif (! $invoice instanceof Invoice) {
            // A `past_due` row without an invoice (created before invoices existed)
            // still has to be chased, otherwise it would silently expire.
            $invoice = $this->invoices->issueNext($subscription);
        }

        $graceEndsAt = $subscription->current_period_end->addDays((int) config('billing.grace_days'));

        if (! $graceEndsAt->greaterThan($now)) {
            $this->subscriptions->markExpired($subscription, $now);

            return;
        }

        $this->dunning->remindIfDue($invoice, $now);
    }
}
