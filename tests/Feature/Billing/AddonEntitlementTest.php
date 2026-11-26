<?php

namespace Tests\Feature\Billing;

use App\Enums\Feature;
use App\Models\Team;
use App\Models\User;
use App\Support\Billing\TenantAddons;
use App\Support\Entitlements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * An add-on extends entitlement without changing the plan label (PDR-06): a Basic
 * tenant that buys `project_management` may open the project board, and loses it
 * again when the add-on ends.
 */
class AddonEntitlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_add_on_grants_the_feature_without_changing_the_plan(): void
    {
        // No subscription: the tenant sits on the default plan (Basic).
        $team = Team::factory()->create();

        $this->assertFalse($team->can(Feature::ProjectManagement));

        app(TenantAddons::class)->grant($team, Feature::ProjectManagement);

        $this->assertTrue($team->can(Feature::ProjectManagement));
        $this->assertContains(Feature::ProjectManagement->value, $team->features());
        $this->assertSame('basic', app(Entitlements::class)->planCode($team));

        app(TenantAddons::class)->revoke($team, Feature::ProjectManagement);

        $this->assertFalse($team->can(Feature::ProjectManagement));
    }

    public function test_a_basic_tenant_with_the_add_on_may_open_the_pro_module(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['role' => 'owner']);
        $owner->switchTeam($team);

        $url = route('projects.index', ['current_team' => $team->slug]);

        $this->actingAs($owner)->get($url)->assertForbidden();

        app(TenantAddons::class)->grant($team, Feature::ProjectManagement);

        $this->actingAs($owner)->get($url)->assertOk();
    }

    public function test_the_plan_defaults_still_apply_beside_the_add_on(): void
    {
        $team = Team::factory()->create();

        app(TenantAddons::class)->grant($team, Feature::Finance);

        $this->assertTrue($team->can(Feature::Finance));
        $this->assertTrue($team->can(Feature::CompanyProfile));
        $this->assertFalse($team->can(Feature::ContentAi));
    }
}
