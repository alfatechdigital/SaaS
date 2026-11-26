<?php

namespace App\Support\Billing;

use App\Models\Invoice;
use App\Models\User;
use App\Notifications\Billing\SubscriptionPaymentReminder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * The three reminders of §B.4.3, sent inside the 7-day grace period.
 *
 * The schedule is configuration (`billing.dunning.days`, days after the due
 * date), and progress is stored on the invoice (`reminders_sent`), not in memory:
 * the daily job may run more than once a day, and a tenant must never receive the
 * same reminder twice.
 *
 * A tenant without an owner is a data problem: the reminder is skipped and logged
 * rather than counted, so it is not silently lost from the dunning sequence.
 */
final class InvoiceDunning
{
    /**
     * Send the next reminder when its day has arrived.
     *
     * @return bool whether a reminder was sent
     */
    public function remindIfDue(Invoice $invoice, CarbonImmutable $now): bool
    {
        if (! $invoice->isOpen()) {
            return false;
        }

        /** @var array<int, int> $schedule */
        $schedule = config('billing.dunning.days');

        $next = $schedule[$invoice->reminders_sent] ?? null;

        if ($next === null) {
            return false;
        }

        $daysPastDue = (int) $invoice->due_at->startOfDay()->diffInDays($now->startOfDay(), false);

        if ($daysPastDue < $next) {
            return false;
        }

        $owner = $invoice->team->owner();

        if (! $owner instanceof User) {
            Log::warning('Skipping a subscription reminder because the tenant has no owner.', [
                'invoice_id' => (int) $invoice->getKey(),
                'team_id' => (int) $invoice->team_id,
            ]);

            return false;
        }

        $owner->notify(new SubscriptionPaymentReminder($invoice, $invoice->team));

        $invoice->reminders_sent = $invoice->reminders_sent + 1;
        $invoice->reminded_at = $now;
        $invoice->save();

        return true;
    }
}
