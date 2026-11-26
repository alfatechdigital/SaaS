<?php

namespace App\Support\Billing;

use App\Contracts\PaymentGateway;
use App\Data\PaymentStatusResult;
use App\Data\PaymentTransaction;
use App\Exceptions\PaymentGatewayError;
use App\Models\Invoice;

/**
 * The gateway used when no provider is configured — the default.
 *
 * It refuses every call instead of pretending. A driver that returned a fake
 * "pending" would let the application believe a payment link exists, and the
 * tenant would wait for a bill nobody can pay. Production must set
 * `BILLING_DRIVER=midtrans` explicitly, the same way WhatsApp requires
 * `WHATSAPP_DRIVER=meta`.
 */
final class UnconfiguredPaymentGateway implements PaymentGateway
{
    public function createTransaction(Invoice $invoice): PaymentTransaction
    {
        throw PaymentGatewayError::notConfigured();
    }

    public function fetchStatus(string $orderId): PaymentStatusResult
    {
        throw PaymentGatewayError::notConfigured();
    }
}
