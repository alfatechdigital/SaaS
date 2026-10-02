<?php

namespace Tests\Feature\Domain;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Shared setup for the team-scoped domain resources.
 */
abstract class DomainTestCase extends TestCase
{
    use RefreshDatabase;

    protected Team $team;

    protected User $owner;

    protected User $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->team = Team::factory()->create();

        $this->owner = User::factory()->create();
        $this->team->members()->attach($this->owner, ['role' => TeamRole::Owner->value]);
        $this->owner->switchTeam($this->team);

        $this->member = User::factory()->create();
        $this->team->members()->attach($this->member, ['role' => TeamRole::Member->value]);
        $this->member->switchTeam($this->team);
    }

    /**
     * Build a route URL for the current team.
     *
     * @param  array<string, mixed>  $parameters
     */
    protected function teamRoute(string $name, array $parameters = []): string
    {
        return route($name, ['current_team' => $this->team->slug, ...$parameters]);
    }
}
