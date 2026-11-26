<?php

namespace App\Support\Billing;

use App\Contracts\PaymentGateway;
use App\Data\PaymentStatusResult;
use App\Exceptions\InvoiceNotPayable;
use App\Exceptions\PaymentGatewayError;
use App\Models\Invoice;

/**
 * The tenant-facing side of paying an invoice (B-2, tugas 23 Nov).
 *
 * Two guarantees this class exists for:
 *
 * 1. **One transaction per invoice.** The payment link is created once and stored
 *    on the invoice; asking again returns the same link instead of asking Midtrans
 *    to open a second transaction for the same period.
 * 2. **Only an open invoice can be paid.** A paid invoice is history and a voided
 *    one no longer describes a debt; both are refused before a provider is called.
 *
 * Reading the status does not change anything. Applying `paid` to the invoice and
 * the subscription belongs to the webhook (tugas 24 Nov) and reconciliation
 * (tugas 25 Nov).
 */
final class InvoicePayment
{
    public function __construct(private readonly PaymentGateway $gateway) {}

    /**
     * Create (or return) the payment link for the invoice.
     *
     * @throws InvoiceNotPayable when the invoice is not open
     * @throws PaymentGatewayError when the provider refuses
     */
    public function start(Invoice $invoice): Invoice
    {
        if (! $invoice->isOpen()) {
            throw InvoiceNotPayable::for($invoice);
        }

        if (is_string($invoice->payment_url) && $invoice->payment_url !== '') {
            return $invoice;
        }

        $transaction = $this->gateway->createTransaction($invoice);

        $invoice->payment_token = $transaction->token;
        $invoice->payment_url = $transaction->redirectUrl;
        $invoice->save();

        return $invoice;
    }

    /**
     * Ask the provider what happened to the invoice's transaction.
     *
     * @throws PaymentGatewayError when the provider cannot answer
     */
    public function refresh(Invoice $invoice): PaymentStatusResult
    {
        return $this->gateway->fetchStatus($invoice->number);
    }
}
