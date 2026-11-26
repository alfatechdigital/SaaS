<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;
use LogicException;

/**
 * Copies the plan catalogue into the `plans` table (B-2).
 *
 * The two config files stay authoritative — `config/plans.php` for what a plan
 * grants and `config/billing.php` for what it costs — and this seeder is the only
 * writer of the projection. Running it again after a price change updates the
 * rows instead of duplicating them.
 */
class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /** @var array<string, array{name: string}> $plans */
        $plans = config('plans.plans');

        /** @var array<string, array{description?: string, price_monthly: int, price_yearly?: int|null}> $prices */
        $prices = config('billing.plans');

        foreach ($plans as $code => $plan) {
            $price = $prices[$code] ?? throw new LogicException(
                "Plan [{$code}] is defined in config/plans.php but has no entry in config/billing.php.",
            );

            Plan::query()->updateOrCreate(['code' => $code], [
                'name' => $plan['name'],
                'description' => $price['description'] ?? null,
                'price_monthly' => $price['price_monthly'],
                'price_yearly' => $price['price_yearly'] ?? null,
                'is_active' => true,
            ]);
        }
    }
}
