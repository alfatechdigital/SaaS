<?php

namespace App\Enums;

/**
 * Direction of a financial transaction.
 */
enum TransactionType: string
{
    case Income = 'income';
    case Expense = 'expense';

    /**
     * Get the display label for the type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Income => 'Pemasukan',
            self::Expense => 'Pengeluaran',
        };
    }

    /**
     * Get all the type values.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
