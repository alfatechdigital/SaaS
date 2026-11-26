<?php

namespace Tests\Feature\Platform;

use App\Models\Plan;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Plan catalogue in the platform panel (F-8, B-2 tugas 26 Nov).
 *
 * Prices are editable because they live in `plans`; features and quotas are read
 * from config and must not be editable (ADR-14, D-06).
 */
class PlatformPlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenants_without_platform_rights_are_forbidden(): void
    {
        $this->seed(PlanSeeder::class);
        $plan = Plan::query()->where('code', 'basic')->sole();
        $owner = User::factory()->create();

        $this->actingAs($owner)->get(route('platform.plans.index'))->assertForbidden();

        $this->actingAs($owner)
            ->patch(route('platform.plans.update', $plan), [
                'price_monthly' => 1000,
                'price_yearly' => 10000,
                'is_active' => true,
            ])
            ->assertForbidden();

        $this->assertSame(149000, $plan->fresh()?->price_monthly);
    }

    public function test_the_page_shows_commercial_and_contractual_sides(): void
    {
        $this->seed(PlanSeeder::class);
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->get(route('platform.plans.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/plans/Index')
                ->has('plans', 3)
                ->where('plans.0.code', 'basic')
                ->where('plans.0.isDefault', true)
                ->where('plans.0.priceMonthly', 149000)
                ->has('plans.0.features')
                ->where('plans.0.limits.users', 1),
            );
    }

    public function test_an_admin_can_change_a_price_and_it_is_audited(): void
    {
        $this->seed(PlanSeeder::class);
        $admin = User::factory()->platformAdmin()->create();
        $plan = Plan::query()->where('code', 'plus')->sole();

        $this->actingAs($admin)
            ->patch(route('platform.plans.update', $plan), [
                'description' => 'Plus dengan harga baru',
                'price_monthly' => 399000,
                'price_yearly' => 3990000,
                'is_active' => false,
            ])
            ->assertRedirect(route('platform.plans.index'));

        $updated = $plan->fresh();

        $this->assertSame(399000, $updated?->price_monthly);
        $this->assertSame(3990000, $updated?->price_yearly);
        $this->assertFalse($updated?->is_active);

        $this->assertDatabaseHas('platform_audit_logs', [
            'action' => 'plan.updated',
            'target_type' => Plan::class,
            'target_id' => (string) $plan->id,
            'actor_id' => $admin->id,
        ]);
    }

    public function test_prices_must_be_whole_rupiah(): void
    {
        $this->seed(PlanSeeder::class);
        $admin = User::factory()->platformAdmin()->create();
        $plan = Plan::query()->where('code', 'basic')->sole();

        $this->actingAs($admin)
            ->patch(route('platform.plans.update', $plan), [
                'price_monthly' => -1,
                'price_yearly' => 'mahal',
                'is_active' => true,
            ])
            ->assertSessionHasErrors(['price_monthly', 'price_yearly']);

        $this->assertSame(149000, $plan->fresh()?->price_monthly);
    }
}
