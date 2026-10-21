<?php

namespace Tests\Feature\Security;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Tests\Feature\Domain\DomainTestCase;

/**
 * Fase 1 / tugas 1.6.1 dan 1.6.2 — mass-assignment audit.
 *
 * `User::$is_platform_admin` and every `team_id` are fillable on purpose: the
 * promote command, seeders, and factories need them. The guarantee tested here
 * is narrower and more important — **no HTTP request can set them**. Form
 * requests only validate known keys, and controllers write `team_id` from the
 * active tenant, so a payload that smuggles either key is simply ignored.
 *
 * @see \docs\implementation\phase-01-fondasi.md tugas 1.6.1, 1.6.2
 */
class MassAssignmentTest extends DomainTestCase
{
    public function test_registration_cannot_grant_platform_admin(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $invitation = TeamInvitation::factory()->create([
            'team_id' => $team->id,
            'email' => 'invited@example.com',
            'invited_by' => $owner->id,
        ]);

        $this->post(route('register.store'), [
            'name' => 'Penyusup',
            'email' => 'invited@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'invitation' => $invitation->code,
            'is_platform_admin' => true,
        ])->assertRedirect();

        $user = User::query()->where('email', 'invited@example.com')->firstOrFail();

        $this->assertFalse($user->isPlatformAdmin());
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'is_platform_admin' => false,
        ]);
    }

    public function test_profile_update_cannot_grant_platform_admin_or_change_tenant(): void
    {
        $otherTeam = Team::factory()->create();

        $this->actingAs($this->member)
            ->patch(route('profile.update'), [
                'name' => 'Nama Baru',
                'email' => $this->member->email,
                'is_platform_admin' => true,
                'is_active' => false,
                'current_team_id' => $otherTeam->id,
            ])
            ->assertRedirect();

        $member = $this->member->fresh();

        $this->assertSame('Nama Baru', $member?->name);
        $this->assertFalse($member?->isPlatformAdmin());
        $this->assertTrue((bool) $member?->is_active);
        $this->assertSame($this->team->id, $member?->current_team_id);
    }

    public function test_domain_create_ignores_a_foreign_team_id(): void
    {
        $otherTeam = Team::factory()->create();

        $this->actingAs($this->owner)
            ->post($this->teamRoute('portfolio.store'), [
                'title' => 'Item Mass Assignment',
                'technologies' => ['Laravel'],
                'featured' => true,
                'published' => true,
                'team_id' => $otherTeam->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('portfolio_items', [
            'title' => 'Item Mass Assignment',
            'team_id' => $this->team->id,
        ]);
        $this->assertDatabaseMissing('portfolio_items', ['team_id' => $otherTeam->id]);
    }
}
