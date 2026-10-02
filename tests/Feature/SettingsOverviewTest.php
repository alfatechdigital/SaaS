<?php

namespace Tests\Feature;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SettingsOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;

        $this->get(route('settings.index', ['current_team' => $team->slug]))
            ->assertRedirect(route('login'));
    }

    public function test_a_team_member_can_view_the_settings_overview(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $owner->switchTeam($team);

        $this->actingAs($owner)
            ->get(route('settings.index', ['current_team' => $team->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/settings/Index')
                ->has('system.database')
                ->has('system.cache')
                ->has('system.queue')
                ->has('system.session')
                ->has('system.mail')
                ->has('system.laravel')
                ->has('system.php'));
    }

    public function test_it_reports_the_configured_database_driver(): void
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $owner->switchTeam($team);

        $this->actingAs($owner)
            ->get(route('settings.index', ['current_team' => $team->slug]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('system.database', config('database.default'))
                ->where('system.laravel', app()->version()));
    }

    public function test_a_non_member_cannot_view_the_settings_overview(): void
    {
        $outsider = User::factory()->create();
        $team = Team::factory()->create();

        $this->actingAs($outsider)
            ->get(route('settings.index', ['current_team' => $team->slug]))
            ->assertForbidden();
    }
}
