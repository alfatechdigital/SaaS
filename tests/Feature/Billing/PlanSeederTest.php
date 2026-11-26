<?php

namespace Tests\Feature\Billing;

use App\Models\Plan;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The `plans` projection must follow the two config files, not drift from them.
 */
class PlanSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_seeder_copies_names_and_prices_from_config(): void
    {
        $this->seed(PlanSeeder::class);

        /** @var array<string, array{name: string}> $plans */
        $plans = config('plans.plans');

        /** @var array<string, array{description?: string, price_monthly: int, price_yearly?: int|null}> $prices */
        $prices = config('billing.plans');

        $this->assertSame(count($plans), Plan::query()->count());

        foreach ($plans as $code => $plan) {
            $row = Plan::findByCode($code);

            $this->assertNotNull($row, "Plan [{$code}] was not seeded.");
            $this->assertSame($plan['name'], $row->name);
            $this->assertSame($prices[$code]['price_monthly'], $row->price_monthly);
            $this->assertSame($prices[$code]['price_yearly'], $row->price_yearly);
            $this->assertSame($prices[$code]['description'] ?? null, $row->description);
            $this->assertTrue($row->is_active);
        }
    }

    public function test_seeding_twice_updates_instead_of_duplicating(): void
    {
        $this->seed(PlanSeeder::class);
        $this->seed(PlanSeeder::class);

        $this->assertSame(count(config('plans.plans')), Plan::query()->count());
    }

    public function test_only_active_plans_are_offered_for_sale(): void
    {
        $this->seed(PlanSeeder::class);

        Plan::query()->where('code', 'basic')->update(['is_active' => false]);

        $this->assertSame(['plus', 'pro'], Plan::query()->active()->orderBy('price_monthly')->pluck('code')->all());
    }
}
