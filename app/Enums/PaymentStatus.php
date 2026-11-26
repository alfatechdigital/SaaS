<?php

namespace App\Enums;

/**
 * What a payment provider says happened to one transaction (B-2, tugas 23 Nov).
 *
 * Provider-agnostic on purpose: Midtrans speaks `settlement`/`capture`/`deny`,
 * another gateway will speak something else, and nothing outside its driver should
 * learn those words. Whether a status *settles an invoice* is a separate question —
 * only `Paid` does, and only a verified webhook (tugas 24 Nov) may act on it.
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Expired = 'expired';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu pembayaran',
            self::Paid => 'Dibayar',
            self::Failed => 'Gagal',
            self::Expired => 'Kedaluwarsa',
            self::Refunded => 'Dikembalikan',
        };
    }

    /**
     * Whether this status means the money arrived.
     */
    public function settlesInvoice(): bool
    {
        return $this === self::Paid;
    }
}
