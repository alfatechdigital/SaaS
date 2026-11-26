<?php

namespace Database\Factories;

use App\Enums\BillingInterval;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * An open invoice for the period that starts now: the row dunning works on.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now();

        return [
            'subscription_id' => Subscription::factory(),
            // Through the query builder on purpose: `subscriptions` is protected by
            // the team global scope, and a factory has no tenant context.
            'team_id' => fn (array $attributes): int => (int) DB::table('subscriptions')
                ->where('id', $attributes['subscription_id'])
                ->value('team_id'),
            'number' => fake()->unique()->numerify('INV-########-###'),
            'status' => InvoiceStatus::Open,
            'billing_interval' => BillingInterval::Monthly,
            'amount' => 149000,
            'currency' => 'IDR',
            'period_start' => $start,
            'period_end' => $start->copy()->addMonthNoOverflow(),
            'due_at' => $start,
            'paid_at' => null,
            'reminders_sent' => 0,
            'reminded_at' => null,
            'provider' => 'midtrans',
            'provider_reference' => null,
        ];
    }

    /**
     * Indicate that the invoice has been paid.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => InvoiceStatus::Paid,
            'paid_at' => now(),
        ]);
    }

    /**
     * Indicate that the invoice was superseded and no longer bills anything.
     */
    public function void(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => InvoiceStatus::Void,
        ]);
    }
}
