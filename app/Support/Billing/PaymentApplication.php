<?php

namespace App\Support\Billing;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Invoice;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

/**
 * Turns "the provider says this transaction is X" into invoice and subscription
 * state (B-2, tugas 25 Nov).
 *
 * Two callers must agree on this, which is why it is one class instead of two
 * copies of the same switch:
 *
 * - the webhook ({@see MidtransWebhookProcessor}), when Midtrans pushes a
 *   notification in real time;
 * - reconciliation ({@see InvoiceReconciler}), when that notification never
 *   arrived and the status has to be pulled.
 *
 * Every step is idempotent — `markPaid()` keeps the first `paid_at`, activation
 * only happens for a subscription that is waiting for one, voiding never touches
 * a paid invoice — so a webhook and a reconcile pass can safely describe the same
 * payment.
 */
final class PaymentApplication
{
    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly SubscriptionService $subscriptions,
    ) {}

    /**
     * Apply one status to the invoice it belongs to.
     *
     * @param  string  $source  who reported it, for the log trail ("webhook", "reconciliation")
     */
    public function apply(Invoice $invoice, PaymentStatus $status, ?string $transactionId, string $source): void
    {
        app(TenantContext::class)->runFor($invoice->team, function () use ($invoice, $status, $transactionId, $source): void {
            switch ($status) {
                case PaymentStatus::Paid:
                    $this->applyPaid($invoice, $transactionId, $source);
                    break;

                case PaymentStatus::Failed:
                case PaymentStatus::Expired:
                    $this->applyUnpaid($invoice, $source);
                    break;

                case PaymentStatus::Refunded:
                    // Money that came back is not a status this application can
                    // decide on its own: the period was already granted, so
                    // revoking it is a manual decision (gelombang 2).
                    Log::warning('A refund/chargeback needs manual follow-up.', [
                        'invoice' => $invoice->number,
                        'source' => $source,
                    ]);
                    break;

                case PaymentStatus::Pending:
                    break;
            }
        });
    }

    private function applyPaid(Invoice $invoice, ?string $transactionId, string $source): void
    {
        $paidAt = CarbonImmutable::now();

        if ($invoice->isOpen()) {
            $this->invoices->markPaid($invoice, $paidAt, $transactionId);
        }

        $subscription = $invoice->subscription;

        if (! in_array($subscription->status, [SubscriptionStatus::Trialing, SubscriptionStatus::PastDue], true)) {
            Log::info('An invoice was settled, but the subscription needs no activation.', [
                'invoice' => $invoice->number,
                'subscription_status' => $subscription->status->value,
                'source' => $source,
            ]);

            return;
        }

        // The invoice decides the period: paying late buys exactly the period that
        // was billed, not a fresh month from the moment the status was learned.
        $this->subscriptions->activate(
            $subscription,
            $invoice->billing_interval,
            $transactionId,
            $paidAt,
            $invoice->period_start,
        );
    }

    private function applyUnpaid(Invoice $invoice, string $source): void
    {
        if ($invoice->isOpen()) {
            $this->invoices->void($invoice);
        }

        Log::info('A payment transaction ended without money.', [
            'invoice' => $invoice->number,
            'source' => $source,
        ]);
    }
}
