<?php

namespace Tests\Feature\Platform;

use App\Enums\Feature;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\TenantAddon;
use App\Models\User;
use App\Support\Billing\TenantAddons;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The platform operator's view of one tenant's billing and its add-ons (F-8,
 * PDR-06). Granting an add-on is the only entitlement change the panel may make;
 * the plan follows the subscription.
 */
class PlatformTenantBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenants_without_platform_rights_are_forbidden(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create();

        $this->actingAs($owner)
            ->get(route('platform.tenants.billing', $team))
            ->assertForbidden();

        $this->actingAs($owner)
            ->post(route('platform.tenants.addons.store', $team), ['feature' => Feature::Finance->value])
            ->assertForbidden();
    }

    public function test_the_page_shows_plan_entitlement_usage_and_add_ons(): void
    {
        $this->seed(PlanSeeder::class);

        $admin = User::factory()->platformAdmin()->create();
        $team = Team::factory()->create();

        Subscription::factory()->for($team)->active()->create(['plan_code' => 'pro']);

        $this->actingAs($admin)
            ->get(route('platform.tenants.billing', $team))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/tenants/Billing')
                ->where('tenant.slug', $team->slug)
                ->where('plan.code', 'pro')
                ->where('plan.priceMonthly', 799000)
                ->where('usage.users.limit', 10)
                ->where('usage.ai.limit', 50)
                ->has('subscription')
                ->has('addons')
                ->has('grantableFeatures'),
            );
    }

    public function test_an_admin_can_grant_an_add_on_and_it_takes_effect(): void
    {
        $this->seed(PlanSeeder::class);

        $admin = User::factory()->platformAdmin()->create();

        $owner = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['role' => 'owner']);

        // Basic does not include the project module, so the add-on is meaningful.
        $this->actingAs($admin)
            ->post(route('platform.tenants.addons.store', $team), [
                'feature' => Feature::ProjectManagement->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tenant_addons', [
            'team_id' => $team->id,
            'feature' => Feature::ProjectManagement->value,
        ]);

        $this->assertDatabaseHas('platform_audit_logs', [
            'action' => 'tenant.addon.granted',
            'target_type' => Team::class,
            'target_id' => (string) $team->id,
            'actor_id' => $admin->id,
        ]);

        // The feature list the tenant gets is the one the panel granted.
        $refreshed = $team->fresh();

        $this->assertNotNull($refreshed);
        $this->assertTrue($refreshed->can(Feature::ProjectManagement));
    }

    public function test_a_feature_already_in_the_plan_is_not_sold_again(): void
    {
        $this->seed(PlanSeeder::class);

        $admin = User::factory()->platformAdmin()->create();
        $team = Team::factory()->create();

        Subscription::factory()->for($team)->active()->create(['plan_code' => 'pro']);

        $this->actingAs($admin)
            ->post(route('platform.tenants.addons.store', $team), [
                'feature' => Feature::Finance->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseMissing('tenant_addons', ['team_id' => $team->id]);
        $this->assertDatabaseMissing('platform_audit_logs', ['action' => 'tenant.addon.granted']);
    }

    public function test_an_admin_can_revoke_an_add_on(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $team = Team::factory()->create();

        app(TenantAddons::class)->grant($team, Feature::Finance);

        $addon = TenantAddon::query()->withoutTeamScope()->where('team_id', $team->id)->sole();

        $this->actingAs($admin)
            ->delete(route('platform.tenants.addons.destroy', [
                'team' => $team,
                'feature' => Feature::Finance->value,
            ]))
            ->assertRedirect();

        $this->assertNotNull($addon->fresh()?->ends_at);
        $this->assertDatabaseHas('platform_audit_logs', [
            'action' => 'tenant.addon.revoked',
            'target_id' => (string) $team->id,
        ]);

        $refreshed = $team->fresh();

        $this->assertNotNull($refreshed);
        $this->assertFalse($refreshed->can(Feature::Finance));
    }

    public function test_the_grantable_list_excludes_what_the_plan_already_covers(): void
    {
        $this->seed(PlanSeeder::class);

        $admin = User::factory()->platformAdmin()->create();
        $team = Team::factory()->create();

        Subscription::factory()->for($team)->active()->create(['plan_code' => 'plus']);

        $this->actingAs($admin)
            ->get(route('platform.tenants.billing', $team))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('grantableFeatures', fn ($features) => collect($features)
                    ->pluck('value')
                    ->contains(Feature::Finance->value) === true
                    && collect($features)
                        ->pluck('value')
                        ->contains(Feature::LeadManagement->value) === false),
            );
    }
}
