<?php

namespace Tests\Feature\Teams;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Support\Billing\SeatQuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Seats are a plan limit (1 / 3 / 10) and are counted, not tracked (B-2, tugas
 * 25 Nov). An invitation holds a seat, so a tenant cannot promise more seats than
 * the plan has.
 */
class SeatQuotaTest extends TestCase
{
    use RefreshDatabase;

    public function test_limits_follow_the_plan_matrix(): void
    {
        $team = Team::factory()->create();
        $quota = app(SeatQuota::class);

        $this->usePlan('basic');
        $this->assertSame(1, $quota->limit($team));

        $this->usePlan('plus');
        $this->assertSame(3, $quota->limit($team));

        $this->usePlan('pro');
        $this->assertSame(10, $quota->limit($team));
    }

    public function test_a_pending_invitation_holds_a_seat(): void
    {
        Notification::fake();

        $this->usePlan('plus');

        [$owner, $team] = $this->tenantWithMembers(2);

        TeamInvitation::factory()->for($team)->create([
            'invited_by' => $owner->id,
            'email' => 'held@example.com',
            'expires_at' => now()->addDays(3),
        ]);

        $this->actingAs($owner)
            ->post($this->inviteUrl($team), ['email' => 'overflow@example.com', 'role' => TeamRole::Member->value])
            ->assertForbidden();

        $this->assertDatabaseMissing('team_invitations', ['email' => 'overflow@example.com']);
    }

    public function test_an_expired_invitation_does_not_hold_a_seat(): void
    {
        Notification::fake();

        $this->usePlan('plus');

        [$owner, $team] = $this->tenantWithMembers(2);

        TeamInvitation::factory()->for($team)->expired()->create([
            'invited_by' => $owner->id,
            'email' => 'gone@example.com',
        ]);

        $this->actingAs($owner)
            ->post($this->inviteUrl($team), ['email' => 'next@example.com', 'role' => TeamRole::Member->value])
            ->assertRedirect();

        $this->assertDatabaseHas('team_invitations', ['email' => 'next@example.com']);
    }

    public function test_accepting_is_refused_when_the_last_seat_was_taken_meanwhile(): void
    {
        $this->usePlan('plus');

        [$owner, $team] = $this->tenantWithMembers(3);

        $invitee = User::factory()->create(['email' => 'late@example.com']);

        $invitation = TeamInvitation::factory()->for($team)->create([
            'invited_by' => $owner->id,
            'email' => $invitee->email,
            'expires_at' => now()->addDays(3),
        ]);

        $this->actingAs($invitee)
            ->post(route('invitations.accept', $invitation))
            ->assertForbidden();

        $this->assertDatabaseMissing('team_members', [
            'team_id' => $team->id,
            'user_id' => $invitee->id,
        ]);
    }

    public function test_the_invitation_being_accepted_does_not_claim_a_second_seat(): void
    {
        $this->usePlan('plus');

        [$owner, $team] = $this->tenantWithMembers(1);

        // Two invitations out, one seat free: the row being accepted must not
        // count as both an invitation and a membership.
        TeamInvitation::factory()->for($team)->create([
            'invited_by' => $owner->id,
            'email' => 'other@example.com',
            'expires_at' => now()->addDays(3),
        ]);

        $invitee = User::factory()->create(['email' => 'welcome@example.com']);

        $invitation = TeamInvitation::factory()->for($team)->create([
            'invited_by' => $owner->id,
            'email' => $invitee->email,
            'expires_at' => now()->addDays(3),
        ]);

        $this->actingAs($invitee)
            ->post(route('invitations.accept', $invitation))
            ->assertRedirect();

        $this->assertDatabaseHas('team_members', [
            'team_id' => $team->id,
            'user_id' => $invitee->id,
        ]);
    }

    /**
     * A tenant whose team holds exactly `$total` members, the first of them owner.
     *
     * @return array{0: User, 1: Team}
     */
    private function tenantWithMembers(int $total): array
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        for ($i = 1; $i < $total; $i++) {
            $member = User::factory()->create();
            $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        }

        $owner->switchTeam($team);

        return [$owner, $team];
    }

    private function inviteUrl(Team $team): string
    {
        return route('team.invitations.store', ['current_team' => $team->slug]);
    }
}
