<?php

namespace Tests;

use App\Contracts\TeamEntitlementSource;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use App\Support\Entitlements;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use Tests\Support\FakeTeamEntitlementSource;

abstract class TestCase extends BaseTestCase
{
    /**
     * Pin the tenant's plan for the rest of the test.
     *
     * Entitlement is a product gate now, not decoration: a test about projects,
     * tasks, contents or finance needs a plan that includes the module. The fake
     * source keeps that decision out of the database, exactly like
     * {@see FakeTeamEntitlementSource} was already used for entitlement tests.
     *
     * @param  string|null  $planCode  a key of `config('plans.plans')`, or null for "no subscription"
     */
    protected function usePlan(?string $planCode): void
    {
        $this->app->instance(TeamEntitlementSource::class, new FakeTeamEntitlementSource($planCode));
        $this->app->forgetInstance(Entitlements::class);
    }

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
