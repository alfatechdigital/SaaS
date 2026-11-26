<?php

namespace App\Data;

use App\Enums\PaymentStatus;

/**
 * The provider's answer to "what happened to this transaction?".
 *
 * Deliberately small: the webhook (tugas 24 Nov) reads the raw payload itself, and
 * nothing else in the application needs provider-specific fields.
 */
final class PaymentStatusResult
{
    public function __construct(
        public readonly PaymentStatus $status,
        public readonly ?string $transactionId = null,
    ) {}
}
