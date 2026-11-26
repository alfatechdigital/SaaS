<?php

namespace App\Support\Billing;

use App\Contracts\PaymentGateway;
use App\Data\PaymentStatusResult;
use App\Data\PaymentTransaction;
use App\Exceptions\PaymentGatewayError;
use App\Models\Invoice;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Midtrans, as the subscription billing provider (D-05).
 *
 * Midtrans is **not** a subscription engine: it creates one payable transaction per
 * order and reports what happened to it. Everything about periods, dunning, and
 * status lives in `subscriptions`/`invoices`; this driver only translates.
 *
 * The invoice number is used as the Midtrans `order_id`, which is what makes a
 * repeated `createTransaction` for the same invoice detectable by the provider
 * instead of charging twice.
 *
 * Sandbox is the default. Production requires both MIDTRANS_IS_PRODUCTION=true and
 * a live server key — a misconfigured environment fails loudly instead of charging
 * the wrong account.
 *
 * @see \docs\implementation\phase-05-fitur-masa-depan.md B-2.4
 */
final class MidtransGateway implements PaymentGateway
{
    /**
     * Snap lives on `app.*`; the status/transaction API lives on `api.*`. They are
     * different hosts, and mixing them up fails with a 404 the provider calls
     * "unexpected", not with a hint about the host.
     */
    private const SNAP_SANDBOX_URL = 'https://app.sandbox.midtrans.com';

    private const SNAP_PRODUCTION_URL = 'https://app.midtrans.com';

    private const API_SANDBOX_URL = 'https://api.sandbox.midtrans.com';

    private const API_PRODUCTION_URL = 'https://api.midtrans.com';

    public function createTransaction(Invoice $invoice): PaymentTransaction
    {
        $response = $this->client()->post($this->snapBaseUrl().'/snap/v1/transactions', [
            'transaction_details' => [
                'order_id' => $invoice->number,
                'gross_amount' => $invoice->amount,
            ],
            'item_details' => [[
                'id' => $invoice->number,
                'price' => $invoice->amount,
                'quantity' => 1,
                'name' => 'Langganan '.$invoice->billing_interval->label(),
            ]],
            // Snap closes the transaction when the grace period is over, so an
            // abandoned payment link cannot be paid after the tenant expired.
            'expiry' => [
                'unit' => 'days',
                'duration' => (int) config('billing.grace_days'),
            ],
        ]);

        if ($response->failed()) {
            throw PaymentGatewayError::requestFailed('Midtrans', $this->describe($response));
        }

        $token = $response->json('token');
        $redirectUrl = $response->json('redirect_url');

        if (! is_string($token) || $token === '' || ! is_string($redirectUrl) || $redirectUrl === '') {
            throw PaymentGatewayError::unexpectedResponse('Midtrans', 'token and redirect_url are missing.');
        }

        return new PaymentTransaction($invoice->number, $token, $redirectUrl);
    }

    public function fetchStatus(string $orderId): PaymentStatusResult
    {
        $response = $this->client()->get($this->apiBaseUrl().'/v2/'.rawurlencode($orderId).'/status');

        if ($response->failed()) {
            throw PaymentGatewayError::requestFailed('Midtrans', $this->describe($response));
        }

        $transactionStatus = $response->json('transaction_status');

        if (! is_string($transactionStatus) || $transactionStatus === '') {
            throw PaymentGatewayError::unexpectedResponse('Midtrans', 'transaction_status is missing.');
        }

        $transactionId = $response->json('transaction_id');

        return new PaymentStatusResult(
            MidtransStatusMap::toPaymentStatus($transactionStatus, $response->json('fraud_status')),
            is_string($transactionId) && $transactionId !== '' ? $transactionId : null,
        );
    }

    private function client(): PendingRequest
    {
        return Http::withBasicAuth($this->serverKey(), '')
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('billing.midtrans.timeout', 15));
    }

    private function snapBaseUrl(): string
    {
        return config('billing.midtrans.is_production') === true
            ? self::SNAP_PRODUCTION_URL
            : self::SNAP_SANDBOX_URL;
    }

    private function apiBaseUrl(): string
    {
        return config('billing.midtrans.is_production') === true
            ? self::API_PRODUCTION_URL
            : self::API_SANDBOX_URL;
    }

    private function serverKey(): string
    {
        $key = config('billing.midtrans.server_key');

        if (! is_string($key) || $key === '') {
            throw PaymentGatewayError::missingServerKey();
        }

        return $key;
    }

    /**
     * A short, secret-free description for the exception message.
     */
    private function describe(Response $response): string
    {
        $message = $response->json('status_message');

        if (is_string($message) && $message !== '') {
            return sprintf('%s (HTTP %d)', $message, $response->status());
        }

        return sprintf('HTTP %d', $response->status());
    }
}
