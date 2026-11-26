<?php

namespace App\Contracts;

use App\Data\PaymentStatusResult;
use App\Data\PaymentTransaction;
use App\Exceptions\PaymentGatewayError;
use App\Models\Invoice;
use App\Support\Billing\InvoicePayment;

/**
 * One payment gateway, behind an interface (B-2, tugas 23 Nov).
 *
 * The application never talks to Midtrans directly: it asks for a transaction and
 * for a status, and the driver translates. That is what keeps provider-specific
 * names (`settlement`, `fraud_status`) out of billing code, and what makes the
 * sandbox safe to run without a network.
 *
 * Two calls only, on purpose. Creating a transaction is idempotent at our level —
 * the invoice number is the provider's order id, so a repeated request cannot
 * charge twice (see {@see InvoicePayment}). Reading a status
 * never changes anything: applying it is the webhook's and reconciliation's job.
 */
interface PaymentGateway
{
    /**
     * Ask the provider to create a payable transaction for an invoice.
     *
     * @throws PaymentGatewayError when not configured or refused
     */
    public function createTransaction(Invoice $invoice): PaymentTransaction;

    /**
     * Ask the provider what happened to the transaction with this order id
     * (our invoice number).
     *
     * @throws PaymentGatewayError when the provider cannot answer
     */
    public function fetchStatus(string $orderId): PaymentStatusResult;
}
