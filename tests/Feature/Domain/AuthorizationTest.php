<?php

namespace Tests\Feature\Domain;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;

class AuthorizationTest extends DomainTestCase
{
    public function test_member_can_view_the_modules_allowed_for_their_role(): void
    {
        $this->actingAs($this->member);

        $this->get($this->teamRoute('dashboard'))->assertOk();
        $this->get($this->teamRoute('projects.index'))->assertOk();
        $this->get($this->teamRoute('contents.index'))->assertOk();
        $this->get($this->teamRoute('portfolio.index'))->assertOk();
        $this->get($this->teamRoute('company-profile.edit'))->assertOk();
    }

    public function test_member_cannot_view_restricted_modules(): void
    {
        $this->actingAs($this->member);

        $this->get($this->teamRoute('leads.index'))->assertForbidden();
        $this->get($this->teamRoute('transactions.index'))->assertForbidden();
        $this->get($this->teamRoute('activity-logs.index'))->assertForbidden();
    }

    public function test_member_cannot_create_records_in_a_restricted_module(): void
    {
        $this->actingAs($this->member);

        $this->post($this->teamRoute('leads.store'), [
            'company_name' => 'PT Terlarang',
            'estimated_value' => 1000,
            'status' => 'new',
        ])->assertForbidden();
    }

    public function test_owner_can_view_every_module(): void
    {
        $this->actingAs($this->owner);

        $this->get($this->teamRoute('projects.index'))->assertOk();
        $this->get($this->teamRoute('leads.index'))->assertOk();
        $this->get($this->teamRoute('contents.index'))->assertOk();
        $this->get($this->teamRoute('transactions.index'))->assertOk();
        $this->get($this->teamRoute('portfolio.index'))->assertOk();
        $this->get($this->teamRoute('company-profile.edit'))->assertOk();
        $this->get($this->teamRoute('activity-logs.index'))->assertOk();
    }

    public function test_admin_can_view_every_module(): void
    {
        $admin = $this->addMemberWithRole(TeamRole::Admin);

        $this->actingAs($admin);

        $this->get($this->teamRoute('leads.index'))->assertOk();
        $this->get($this->teamRoute('transactions.index'))->assertOk();
        $this->get($this->teamRoute('activity-logs.index'))->assertOk();
    }

    public function test_member_of_another_team_is_rejected(): void
    {
        $outsider = $this->userInAnotherTeam();

        $this->actingAs($outsider);

        $this->get($this->teamRoute('projects.index'))->assertForbidden();
        $this->get($this->teamRoute('leads.index'))->assertForbidden();
        $this->get($this->teamRoute('dashboard'))->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get($this->teamRoute('projects.index'))->assertRedirect();
    }

    /**
     * Add a new user to the team with the given role.
     */
    private function addMemberWithRole(TeamRole $role): User
    {
        $user = User::factory()->create();

        $this->team->members()->attach($user, ['role' => $role->value]);
        $user->switchTeam($this->team);

        return $user;
    }

    /**
     * Create a user that belongs to a different team only.
     */
    private function userInAnotherTeam(): User
    {
        $team = Team::factory()->create();

        $user = User::factory()->create();
        $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
        $user->switchTeam($team);

        return $user;
    }
}
