<?php

namespace Tests\Feature\Teams;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Basic allows one user, so the team page and every member/invitation endpoint
 * are refused — not merely hidden (F-7, keputusan 2026-10-28).
 */
class TeamPlanLimitTest extends TestCase
{
    use RefreshDatabase;

    private function useBasicPlan(): void
    {
        $this->usePlan('basic');
    }

    private function useMultiUserPlan(): void
    {
        $this->usePlan('plus');
    }

    /**
     * @return array{0: User, 1: Team}
     */
    private function tenant(TeamRole $role = TeamRole::Owner): array
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($user, ['role' => $role->value]);
        $user->switchTeam($team);

        return [$user, $team];
    }

    public function test_a_basic_owner_cannot_open_the_team_page(): void
    {
        $this->useBasicPlan();
        [$owner, $team] = $this->tenant();

        $this->actingAs($owner)
            ->get(route('team.index', ['current_team' => $team->slug]))
            ->assertForbidden();
    }

    public function test_a_basic_owner_cannot_invite_anyone(): void
    {
        $this->useBasicPlan();
        Notification::fake();
        [$owner, $team] = $this->tenant();

        $this->actingAs($owner)
            ->post(route('team.invitations.store', ['current_team' => $team->slug]), [
                'email' => 'teman@example.test',
                'role' => TeamRole::Member->value,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('team_invitations', 0);
        Notification::assertNothingSent();
    }

    public function test_a_basic_owner_cannot_change_another_member(): void
    {
        $this->useBasicPlan();
        [$owner, $team] = $this->tenant();
        $member = User::factory()->create();

        // A second member should not exist on Basic, but the endpoint must not
        // depend on that invariant being true in order to refuse the request.
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);

        $this->actingAs($owner)
            ->patch(route('team.members.update', [
                'current_team' => $team->slug,
                'user' => $member->id,
            ]), ['role' => TeamRole::Admin->value])
            ->assertForbidden();
    }

    public function test_a_multi_user_plan_can_open_the_team_page(): void
    {
        $this->useMultiUserPlan();
        [$owner, $team] = $this->tenant();

        $this->actingAs($owner)
            ->get(route('team.index', ['current_team' => $team->slug]))
            ->assertOk();
    }
}
