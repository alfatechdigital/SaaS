<?php

namespace Tests\Feature\Teams;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The tenant's team page lives inside the tenant shell now (`{current_team}/team`).
 *
 * Tenant creation/deletion moved to the platform panel — see
 * tests/Feature/Platform/PlatformTenantTest.php. "Leave team" no longer exists:
 * with invitation-only onboarding there is nowhere to leave *to* (PDR-06, PDR-08).
 */
class TeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_tenant_team_page_renders()
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['name' => 'Alfatech']);
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $response = $this
            ->actingAs($owner)
            ->get(route('team.index', ['current_team' => $team->slug]));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                // The `admin/` prefix is what makes app.ts pick AdminLayout, so
                // this component name is the contract that keeps the tenant shell.
                ->component('admin/team/Index')
                ->where('team.slug', $team->slug)
                ->where('team.name', 'Alfatech')
                ->where('members.0.role', TeamRole::Owner->value)
                ->where('members.0.role_label', TeamRole::Owner->label()),
            );
    }

    public function test_non_members_cannot_open_the_tenant_team_page()
    {
        $outsider = User::factory()->create();
        $team = Team::factory()->create();

        $this
            ->actingAs($outsider)
            ->get(route('team.index', ['current_team' => $team->slug]))
            ->assertForbidden();
    }

    public function test_guests_cannot_open_the_tenant_team_page()
    {
        $team = Team::factory()->create();

        $this
            ->get(route('team.index', ['current_team' => $team->slug]))
            ->assertRedirect(route('login'));
    }

    public function test_the_team_can_be_renamed_by_owners()
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['name' => 'Original Name']);
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $response = $this
            ->actingAs($owner)
            ->patch(route('team.update', ['current_team' => $team->slug]), [
                'name' => 'Updated Name',
            ]);

        // Renaming regenerates the slug, so the redirect points at the new one.
        $response->assertRedirect(route('team.index', ['current_team' => $team->fresh()->slug]));

        $this->assertDatabaseHas('teams', [
            'id' => $team->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_the_team_cannot_be_renamed_by_members()
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);

        $this
            ->actingAs($member)
            ->patch(route('team.update', ['current_team' => $team->slug]), [
                'name' => 'Renamed By Member',
            ])
            ->assertForbidden();
    }

    public function test_personal_team_returns_the_team_owned_by_the_user()
    {
        $otherUser = User::factory()->create();
        $user = User::factory()->make();
        $user->save();

        $otherUser->personalTeam()->members()->attach($user, [
            'role' => TeamRole::Member->value,
        ]);

        $personalTeam = Team::factory()->personal()->create();
        $personalTeam->members()->attach($user, [
            'role' => TeamRole::Owner->value,
        ]);

        $this->assertTrue($personalTeam->is($user->personalTeam()));
    }

    /**
     * Guards PDR-07 ("opsi c") + PDR-08: the starter kit team screens AND the team
     * switcher must stay deleted.
     *
     * Without this, re-adding one of these routes would silently bring back the
     * layout switch this whole change set set out to remove (TBD-08 / M-5), or
     * re-introduce multi-tenant membership that PDR-10 forbids.
     */
    public function test_the_starter_kit_team_routes_are_gone()
    {
        foreach ([
            'teams.index',
            'teams.store',
            'teams.edit',
            'teams.update',
            'teams.destroy',
            'teams.leave',
            'teams.switch',
            'teams.members.update',
            'teams.members.destroy',
            'teams.invitations.store',
            'teams.invitations.destroy',
        ] as $route) {
            $this->assertFalse(Route::has($route), "Route [{$route}] should no longer exist.");
        }
    }
}
