<?php

namespace Tests\Feature\Tenancy;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Guards the removal of the starter kit's "personal team" concept.
 *
 * Onboarding is invitation-only (PDR-06), so every `Team` is a tenant and a user
 * gets one only by joining one. These tests keep the concept from creeping back
 * — the schema, the factories, and the platform payload.
 *
 * @see \docs\implementation\cleanup-personal-team.md
 */
class NoPersonalTeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_teams_table_has_no_personal_flag(): void
    {
        $this->assertFalse(Schema::hasColumn('teams', 'is_personal'));
    }

    public function test_the_user_factory_does_not_create_a_team(): void
    {
        $user = User::factory()->create();

        $this->assertSame(0, $user->teams()->count());
        $this->assertNull($user->current_team_id);
        $this->assertDatabaseCount('teams', 0);
    }

    public function test_a_tenant_is_created_only_through_an_explicit_action(): void
    {
        $user = User::factory()->create();
        $team = $this->createTeamFor($user);

        $this->assertSame(1, Team::query()->count());
        $this->assertTrue($user->fresh()->isCurrentTeam($team));
    }

    public function test_the_platform_tenant_payload_has_no_personal_flag(): void
    {
        $admin = User::factory()->platformAdmin()->create();
        $this->createTeamFor($admin, attributes: ['name' => 'Tenant Biasa']);

        $this->actingAs($admin)
            ->get(route('platform.tenants.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('platform/tenants/Index')
                ->missing('tenants.0.isPersonal'));
    }
}
