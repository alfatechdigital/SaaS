<?php

namespace Database\Factories;

use App\Enums\BillingInterval;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscription>
 */
class SubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * A plain paid subscription in the middle of its period: the most common row
     * the billing code will meet.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->subDays(10);

        return [
            'team_id' => Team::factory(),
            'plan_code' => (string) config('plans.default_plan', 'basic'),
            'billing_interval' => BillingInterval::Monthly,
            'status' => SubscriptionStatus::Active,
            'trial_ends_at' => null,
            'current_period_start' => $start,
            'current_period_end' => $start->copy()->addMonth(),
            'canceled_at' => null,
            'provider' => (string) config('billing.provider', 'midtrans'),
            'provider_reference' => null,
            'ended_at' => null,
        ];
    }

    /**
     * Indicate that the subscription is still in its trial period.
     */
    public function trialing(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SubscriptionStatus::Trialing,
            'billing_interval' => null,
            'trial_ends_at' => now()->addDays((int) config('billing.trial_days', 30)),
        ]);
    }

    /**
     * Indicate that the subscription is paid and current.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SubscriptionStatus::Active,
            'trial_ends_at' => null,
        ]);
    }

    /**
     * Indicate that the period's invoice was not paid and the grace period runs.
     */
    public function pastDue(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SubscriptionStatus::PastDue,
        ]);
    }

    /**
     * Indicate that the subscription was canceled and has ended.
     */
    public function canceled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SubscriptionStatus::Canceled,
            'canceled_at' => now(),
            'ended_at' => now(),
        ]);
    }

    /**
     * Indicate that the grace period ran out and the subscription ended.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SubscriptionStatus::Expired,
            'ended_at' => now(),
        ]);
    }
}
