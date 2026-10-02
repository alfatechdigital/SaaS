<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers `platform:promote` — the only way to grant platform rights on an
 * installation that was not freshly seeded. See F-7-6.
 */
class PlatformPromoteCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_promotes_an_existing_user()
    {
        $user = User::factory()->create(['email' => 'operator@example.com']);

        $this->assertFalse($user->isPlatformAdmin());

        $this->artisan('platform:promote', ['email' => 'operator@example.com'])
            ->assertSuccessful();

        $this->assertTrue($user->fresh()->isPlatformAdmin());
    }

    public function test_it_matches_the_email_case_insensitively()
    {
        $user = User::factory()->create(['email' => 'operator@example.com']);

        $this->artisan('platform:promote', ['email' => 'OPERATOR@example.com'])
            ->assertSuccessful();

        $this->assertTrue($user->fresh()->isPlatformAdmin());
    }

    public function test_it_fails_for_an_unknown_email()
    {
        User::factory()->create(['email' => 'operator@example.com']);

        $this->artisan('platform:promote', ['email' => 'nobody@example.com'])
            ->assertExitCode(1);
    }

    public function test_it_can_revoke_platform_rights()
    {
        $user = User::factory()->platformAdmin()->create();

        $this->artisan('platform:promote', ['email' => $user->email, '--revoke' => true])
            ->assertSuccessful();

        $this->assertFalse($user->fresh()->isPlatformAdmin());
    }

    public function test_it_is_idempotent_for_an_existing_platform_admin()
    {
        $user = User::factory()->platformAdmin()->create();

        $this->artisan('platform:promote', ['email' => $user->email])
            ->assertSuccessful();

        $this->assertTrue($user->fresh()->isPlatformAdmin());
    }
}
