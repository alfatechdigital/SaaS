<?php

namespace Tests;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /**
     * Create a tenant and make the given user its member.
     *
     * `UserFactory` deliberately creates no team: onboarding is invitation-only
     * (PDR-06), so a user only ever gets a tenant through an explicit action.
     * Tests that need a tenant therefore ask for one — see
     * docs/implementation/cleanup-personal-team.md (PT-4).
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function createTeamFor(User $user, TeamRole $role = TeamRole::Owner, array $attributes = []): Team
    {
        $team = Team::factory()->create($attributes);

        $team->members()->attach($user, ['role' => $role->value]);
        $user->switchTeam($team);

        return $team;
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
