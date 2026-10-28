<?php

namespace Tests\Feature\Teams;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneExpiredTeamInvitationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_invitations_are_deleted_by_the_scheduled_cleanup(): void
    {
        $this->travelTo(now()->startOfDay());

        $owner = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $expiredInvitation = TeamInvitation::factory()->expired()->create([
            'team_id' => $team->id,
            'invited_by' => $owner->id,
        ]);

        $unexpiredInvitation = TeamInvitation::factory()->expiresIn(1)->create([
            'team_id' => $team->id,
            'invited_by' => $owner->id,
        ]);

        $invitationWithoutExpiration = TeamInvitation::factory()->create([
            'team_id' => $team->id,
            'invited_by' => $owner->id,
        ]);

        $this->artisan('schedule:run')->assertSuccessful();

        $this->assertDatabaseMissing('team_invitations', [
            'id' => $expiredInvitation->id,
        ]);

        $this->assertDatabaseHas('team_invitations', [
            'id' => $unexpiredInvitation->id,
        ]);

        $this->assertDatabaseHas('team_invitations', [
            'id' => $invitationWithoutExpiration->id,
        ]);
    }

    /**
     * Fase 2, tugas 2.6.4 — the scheduled cleanup is cross-tenant on purpose.
     *
     * `schedule:run` executes with no tenant context, so a tenant-scoped model
     * here would throw. `team_invitations` is not tenant-scoped, and this test
     * pins the consequence: one global sweep reaches every tenant, and adding
     * tenants does not require iterating them.
     */
    public function test_the_scheduled_cleanup_reaches_every_tenant_at_once(): void
    {
        $this->travelTo(now()->startOfDay());

        $owner = User::factory()->create();

        $teams = [
            Team::factory()->create(),
            Team::factory()->create(),
        ];

        $expired = collect($teams)->map(fn (Team $team): TeamInvitation => TeamInvitation::factory()->expired()->create([
            'team_id' => $team->id,
            'invited_by' => $owner->id,
        ]));

        $this->assertNull(app(TenantContext::class)->team());

        $this->artisan('schedule:run')->assertSuccessful();

        foreach ($expired as $invitation) {
            $this->assertDatabaseMissing('team_invitations', ['id' => $invitation->id]);
        }
    }
}
