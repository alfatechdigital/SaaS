<?php

namespace Tests\Feature\Platform;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Covers the platform layer (PDR-03, PDR-05): an installation-wide capability
 * that must never be reachable from a tenant role alone.
 */
class PlatformTenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login()
    {
        $this->get(route('platform.tenants.index'))->assertRedirect(route('login'));
    }

    public function test_tenant_owners_without_platform_rights_are_forbidden()
    {
        // A regular factory user owns their own personal team, i.e. they are an
        // Owner in tenant terms. That must NOT grant platform rights.
        $owner = User::factory()->create();

        $this->actingAs($owner)
            ->get(route('platform.tenants.index'))
            ->assertForbidden();

        $this->actingAs($owner)
            ->post(route('platform.tenants.store'), ['name' => 'Sneaky Tenant'])
            ->assertForbidden();

        $this->assertDatabaseMissing('teams', ['name' => 'Sneaky Tenant']);
    }

    public function test_platform_admin_can_see_every_tenant()
    {
        $admin = User::factory()->platformAdmin()->create();
        Team::factory()->create(['name' => 'Tahutech']);

        $response = $this->actingAs($admin)->get(route('platform.tenants.index'));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/tenants/Index')
                ->where(
                    'tenants',
                    fn ($tenants) => collect($tenants)->pluck('name')->contains('Tahutech'),
                ),
            );
    }

    public function test_platform_admin_can_create_a_tenant()
    {
        $admin = User::factory()->platformAdmin()->create();

        $response = $this->actingAs($admin)
            ->post(route('platform.tenants.store'), ['name' => 'Klien Baru']);

        $response->assertRedirect(route('platform.tenants.index'));

        $this->assertDatabaseHas('teams', [
            'name' => 'Klien Baru',
            'is_personal' => false,
        ]);
    }

    public function test_creating_a_tenant_does_not_move_the_operator_into_it()
    {
        $admin = User::factory()->platformAdmin()->create();
        $ownTeamId = $admin->currentTeam?->id;

        $this->assertNotNull($ownTeamId);

        $this->actingAs($admin)
            ->post(route('platform.tenants.store'), ['name' => 'Klien Baru']);

        $this->assertEquals($ownTeamId, $admin->fresh()->current_team_id);
    }

    public function test_creating_a_tenant_requires_a_name()
    {
        $admin = User::factory()->platformAdmin()->create();

        $this->actingAs($admin)
            ->post(route('platform.tenants.store'), ['name' => ''])
            ->assertSessionHasErrors('name');
    }

    public function test_platform_admin_can_delete_a_tenant()
    {
        $admin = User::factory()->platformAdmin()->create();
        $owner = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $response = $this
            ->actingAs($admin)
            ->delete(route('platform.tenants.destroy', $team));

        $response->assertRedirect(route('platform.tenants.index'));

        $this->assertSoftDeleted('teams', ['id' => $team->id]);
        $this->assertDatabaseMissing('team_members', ['team_id' => $team->id]);
    }

    public function test_non_platform_admins_cannot_delete_a_tenant()
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $this
            ->actingAs($owner)
            ->delete(route('platform.tenants.destroy', $team))
            ->assertForbidden();

        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'deleted_at' => null,
        ]);
    }

    public function test_personal_teams_cannot_be_deleted_from_the_platform_panel()
    {
        $admin = User::factory()->platformAdmin()->create();
        $personalTeam = $admin->personalTeam();

        $this->assertNotNull($personalTeam);

        $this
            ->actingAs($admin)
            ->delete(route('platform.tenants.destroy', $personalTeam))
            ->assertForbidden();
    }

    public function test_platform_admin_can_deactivate_a_tenants_public_page()
    {
        $admin = User::factory()->platformAdmin()->create();
        $team = Team::factory()->create();

        $response = $this
            ->actingAs($admin)
            ->patch(route('platform.tenants.public-page', $team), ['enabled' => false]);

        $response->assertRedirect(route('platform.tenants.index'));

        $this->assertFalse($team->fresh()->public_page_enabled);
    }

    public function test_platform_admin_can_reactivate_a_tenants_public_page()
    {
        $admin = User::factory()->platformAdmin()->create();
        $team = Team::factory()->create(['public_page_enabled' => false]);

        $this
            ->actingAs($admin)
            ->patch(route('platform.tenants.public-page', $team), ['enabled' => true])
            ->assertRedirect(route('platform.tenants.index'));

        $this->assertTrue($team->fresh()->public_page_enabled);
    }

    public function test_non_platform_admins_cannot_change_the_public_page_status()
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $this
            ->actingAs($owner)
            ->patch(route('platform.tenants.public-page', $team), ['enabled' => false])
            ->assertForbidden();

        $this->assertTrue($team->fresh()->public_page_enabled);
    }

    public function test_the_public_page_status_is_exposed_to_the_panel()
    {
        $admin = User::factory()->platformAdmin()->create();
        Team::factory()->create(['name' => 'Offline Co', 'public_page_enabled' => false]);

        $response = $this->actingAs($admin)->get(route('platform.tenants.index'));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where(
                    'tenants',
                    fn ($tenants) => collect($tenants)->contains(
                        fn ($tenant) => $tenant['name'] === 'Offline Co'
                            && $tenant['publicPageEnabled'] === false,
                    ),
                ),
            );
    }

    public function test_deleting_a_tenant_clears_the_current_team_of_members_with_nowhere_to_go()
    {
        $admin = User::factory()->platformAdmin()->create();

        // Invitation-only onboarding means a member may have no personal team,
        // so there is genuinely nowhere for them to fall back to (PDR-06).
        $member = User::factory()->create();
        $member->personalTeam()?->delete();

        $team = Team::factory()->create();
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $member->update(['current_team_id' => $team->id]);

        $this
            ->actingAs($admin)
            ->delete(route('platform.tenants.destroy', $team))
            ->assertRedirect(route('platform.tenants.index'));

        $this->assertNull($member->fresh()->current_team_id);
    }
}
