<?php

namespace App\Support\Billing;

use App\Enums\PaymentStatus;

/**
 * One verified Midtrans notification (B-2, tugas 24 Nov).
 *
 * Built from the raw POST body, and it keeps the exact strings Midtrans signed:
 * the signature is computed over `order_id + status_code + gross_amount + server_key`,
 * so formatting `gross_amount` differently before hashing would reject a valid
 * notification. `amount()` is a separate, lossy parse used only for comparison.
 */
final class MidtransNotification
{
    /**
     * @param  array<string, mixed>  $payload
     */
    private function __construct(
        public readonly string $orderId,
        public readonly string $statusCode,
        public readonly string $grossAmount,
        public readonly string $transactionStatus,
        public readonly ?string $transactionId,
        public readonly PaymentStatus $status,
        public readonly string $signatureKey,
        public readonly array $payload,
    ) {}

    /**
     * Build a notification, or `null` when the payload is not a complete one.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromArray(array $payload): ?self
    {
        $orderId = $payload['order_id'] ?? null;
        $statusCode = $payload['status_code'] ?? null;
        $grossAmount = $payload['gross_amount'] ?? null;
        $transactionStatus = $payload['transaction_status'] ?? null;
        $signatureKey = $payload['signature_key'] ?? null;

        foreach ([$orderId, $statusCode, $grossAmount, $transactionStatus, $signatureKey] as $value) {
            if (! is_string($value) || $value === '') {
                return null;
            }
        }

        $transactionId = $payload['transaction_id'] ?? null;

        return new self(
            orderId: (string) $orderId,
            statusCode: (string) $statusCode,
            grossAmount: (string) $grossAmount,
            transactionStatus: (string) $transactionStatus,
            transactionId: is_string($transactionId) && $transactionId !== '' ? $transactionId : null,
            status: MidtransStatusMap::toPaymentStatus((string) $transactionStatus, $payload['fraud_status'] ?? null),
            signatureKey: (string) $signatureKey,
            payload: $payload,
        );
    }

    /**
     * Whether the payload was signed with the configured server key.
     *
     * `hash_equals` keeps the comparison constant-time: a signature check that
     * leaks how much of the guess was right is still a signature check an attacker
     * can walk.
     */
    public function hasValidSignature(string $serverKey): bool
    {
        $expected = hash('sha512', $this->orderId.$this->statusCode.$this->grossAmount.$serverKey);

        return hash_equals($expected, $this->signatureKey);
    }

    /**
     * The amount as whole rupiah, for comparing with the invoice.
     *
     * Midtrans sends `"349000.00"`; rounding is safe because our prices are whole
     * rupiah (§B.4.3) and a fractional rupiah cannot exist here.
     */
    public function amount(): int
    {
        return (int) round((float) $this->grossAmount);
    }
}
