<?php

namespace App\Enums;

/**
 * Lifecycle of one subscription invoice (B-2, tugas 19 Nov).
 *
 * Only three states, because the invoice is a *record*, not a workflow: it is
 * issued open, settles to `paid`, or is canceled with `void` when it no longer
 * describes what the tenant owes (a late payment replaces the period it was
 * issued for). Overdue is a question of time, not of status — see
 * `Invoice::isOverdue()`.
 */
enum InvoiceStatus: string
{
    case Open = 'open';
    case Paid = 'paid';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Belum dibayar',
            self::Paid => 'Lunas',
            self::Void => 'Dibatalkan',
        };
    }

    /**
     * Whether the invoice still expects a payment.
     */
    public function isOpen(): bool
    {
        return $this === self::Open;
    }

    /**
     * Whether nothing can change anymore: a paid or voided invoice is history.
     */
    public function isFinal(): bool
    {
        return ! $this->isOpen();
    }
}
