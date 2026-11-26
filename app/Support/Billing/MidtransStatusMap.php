<?php

namespace App\Support\Billing;

use App\Enums\PaymentStatus;
use Illuminate\Support\Facades\Log;

/**
 * Maps Midtrans' transaction vocabulary into ours.
 *
 * Shared by the outbound driver (`fetchStatus`) and the inbound webhook, so a
 * status can never mean two different things depending on how we learned it.
 *
 * `capture` is only money when the fraud check accepted it; a challenged card stays
 * pending. An unfamiliar status is treated as pending — never as paid — and logged,
 * because granting access on a word we do not understand would be a guess with a
 * paying tenant's account behind it.
 */
final class MidtransStatusMap
{
    public static function toPaymentStatus(string $transactionStatus, mixed $fraudStatus): PaymentStatus
    {
        return match ($transactionStatus) {
            'settlement' => PaymentStatus::Paid,
            'capture' => $fraudStatus === 'accept' ? PaymentStatus::Paid : PaymentStatus::Pending,
            'pending', 'authorize' => PaymentStatus::Pending,
            'deny', 'cancel', 'failure' => PaymentStatus::Failed,
            'expire' => PaymentStatus::Expired,
            'refund', 'partial_refund', 'chargeback', 'partial_chargeback' => PaymentStatus::Refunded,
            default => self::unknown($transactionStatus),
        };
    }

    private static function unknown(string $transactionStatus): PaymentStatus
    {
        Log::warning(
            'Midtrans reported an unknown transaction status; treating it as pending.',
            ['transaction_status' => $transactionStatus],
        );

        return PaymentStatus::Pending;
    }
}
