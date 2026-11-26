<?php

namespace App\Support\Billing;

use App\Models\Invoice;
use App\Models\PaymentEvent;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Applies one verified Midtrans notification (B-2, tugas 24 Nov).
 *
 * The provider only reports a transaction; what it means for the invoice and the
 * subscription is decided by {@see PaymentApplication}. This class owns the
 * delivery problems instead:
 *
 * 1. **Unknown order** → log and accept. It is not ours to apply, and a non-2xx
 *    answer would make Midtrans retry forever.
 * 2. **Duplicate delivery** → the event row already reached a decision, so stop.
 * 3. **Amount mismatch** → record the decision and do not touch money. A signed
 *    payload with the wrong amount means configuration drift, not a payment.
 * 4. **Apply** → paid invoices settle and (if the subscription is waiting for a
 *    payment) it activates for exactly the invoiced period; failed/expired
 *    transactions void the invoice so dunning stops chasing a dead link.
 *
 * Failures are re-thrown after being recorded, so the controller answers 5xx and
 * Midtrans retries; every apply step is idempotent, so the retry is safe.
 */
final class MidtransWebhookProcessor
{
    public function __construct(private readonly PaymentApplication $application) {}

    /**
     * @throws Throwable when applying failed; the caller must answer 5xx so the
     *                   provider retries
     */
    public function process(MidtransNotification $notification): void
    {
        $invoice = Invoice::query()
            ->withoutTeamScope()
            ->where('number', $notification->orderId)
            ->first();

        if (! $invoice instanceof Invoice) {
            Log::warning('Ignoring a Midtrans notification for an unknown order.', [
                'order_id' => $notification->orderId,
                'transaction_status' => $notification->transactionStatus,
            ]);

            return;
        }

        $event = PaymentEvent::query()->firstOrCreate(
            [
                'provider' => 'midtrans',
                'order_id' => $notification->orderId,
                'transaction_id' => $notification->transactionId,
                'transaction_status' => $notification->transactionStatus,
            ],
            ['payload' => $notification->payload],
        );

        if ($event->isProcessed()) {
            Log::info('Ignoring a duplicate Midtrans notification.', [
                'order_id' => $notification->orderId,
                'transaction_status' => $notification->transactionStatus,
            ]);

            return;
        }

        if ($notification->amount() !== $invoice->amount) {
            $this->decide($event, sprintf(
                'Amount mismatch: notification says %d, invoice says %d.',
                $notification->amount(),
                $invoice->amount,
            ));

            Log::error('Refusing to settle an invoice for a different amount.', [
                'invoice' => $invoice->number,
                'notified_amount' => $notification->amount(),
                'invoice_amount' => $invoice->amount,
            ]);

            return;
        }

        try {
            $this->application->apply(
                $invoice,
                $notification->status,
                $notification->transactionId,
                'webhook',
            );
        } catch (Throwable $exception) {
            $event->fill(['error' => Str::limit($exception->getMessage(), 250)])->save();

            throw $exception;
        }

        $event->fill([
            'processed_at' => CarbonImmutable::now(),
            'error' => null,
        ])->save();
    }

    /**
     * Record a final decision that is not a status change (e.g. an amount mismatch).
     */
    private function decide(PaymentEvent $event, string $reason): void
    {
        $event->fill([
            'processed_at' => CarbonImmutable::now(),
            'error' => Str::limit($reason, 250),
        ])->save();
    }
}
