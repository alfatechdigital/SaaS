<?php

namespace App\Enums;

/**
 * Accounting category of a financial transaction.
 */
enum TransactionCategory: string
{
    case ProjectIncome = 'project_income';
    case ProjectExpense = 'project_expense';
    case CompanyExpense = 'company_expense';
    case Capital = 'capital';
    case Other = 'other';

    /**
     * Get the display label for the category.
     */
    public function label(): string
    {
        return match ($this) {
            self::ProjectIncome => 'Pendapatan Proyek',
            self::ProjectExpense => 'Pengeluaran Proyek',
            self::CompanyExpense => 'Pengeluaran Perusahaan',
            self::Capital => 'Modal',
            self::Other => 'Lainnya',
        };
    }

    /**
     * Get the type that this category belongs to.
     */
    public function type(): TransactionType
    {
        return match ($this) {
            self::ProjectIncome => TransactionType::Income,
            self::ProjectExpense, self::CompanyExpense => TransactionType::Expense,
            self::Capital => TransactionType::Income,
            self::Other => TransactionType::Expense,
        };
    }

    /**
     * Get all the category values.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
