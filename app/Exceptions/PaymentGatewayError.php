<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a payment gateway is missing configuration or refuses a request.
 *
 * Failing loudly is the point. A payment that silently "did not happen" leaves an
 * invoice open and a tenant in the dark, so nothing here degrades to a fake
 * success. Messages never include the server key.
 */
final class PaymentGatewayError extends RuntimeException
{
    public static function notConfigured(): self
    {
        return new self(
            'No payment gateway is configured. Set BILLING_DRIVER=midtrans and provide MIDTRANS_SERVER_KEY.',
        );
    }

    public static function missingServerKey(): self
    {
        return new self('MIDTRANS_SERVER_KEY is empty; the Midtrans gateway cannot authenticate.');
    }

    public static function requestFailed(string $gateway, string $detail): self
    {
        return new self(sprintf('%s refused the request: %s', $gateway, $detail));
    }

    public static function unexpectedResponse(string $gateway, string $detail): self
    {
        return new self(sprintf('%s returned an unexpected response: %s', $gateway, $detail));
    }
}
