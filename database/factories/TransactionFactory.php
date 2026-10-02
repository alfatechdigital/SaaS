<?php

namespace Database\Factories;

use App\Enums\TransactionCategory;
use App\Enums\TransactionType;
use App\Models\Team;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $category = fake()->randomElement(TransactionCategory::cases());

        return [
            'team_id' => Team::factory(),
            'type' => $category->type(),
            'category' => $category,
            'project_id' => null,
            'description' => fake()->sentence(6),
            'amount' => fake()->numberBetween(1, 60) * 1_000_000,
            'date' => fake()->dateTimeBetween('-3 months', 'now'),
            'created_by_id' => null,
        ];
    }

    /**
     * Indicate that the transaction is an income.
     */
    public function income(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TransactionType::Income,
            'category' => TransactionCategory::ProjectIncome,
        ]);
    }

    /**
     * Indicate that the transaction is an expense.
     */
    public function expense(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TransactionType::Expense,
            'category' => TransactionCategory::CompanyExpense,
        ]);
    }
}
