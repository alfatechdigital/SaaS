<?php

namespace Tests\Feature\Auth;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_login_page_does_not_offer_signup_without_an_invitation()
    {
        // PDR-06: registration is invitation-only, so advertising "Sign up" on the
        // login screen would only bounce people back to the same screen.
        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('Sign up');
    }

    public function test_registration_is_unavailable_without_an_invitation()
    {
        // PDR-06: self-service signup is closed.
        $response = $this->get(route('register'));

        $response->assertRedirect(route('login'));
    }

    public function test_registration_screen_includes_team_invitation_context()
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['name' => 'Laravel Team']);
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $invitation = TeamInvitation::factory()->create([
            'team_id' => $team->id,
            'email' => 'invited@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this->get(route('register', ['invitation' => $invitation->code]));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('auth/Register')
            ->where('teamInvitation.code', $invitation->code)
            ->where('teamInvitation.teamName', 'Laravel Team'),
        );
    }

    public function test_new_users_can_register_only_by_accepting_an_invitation()
    {
        $owner = User::factory()->create();
        $team = Team::factory()->create(['name' => 'Laravel Team']);
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        $invitation = TeamInvitation::factory()->create([
            'team_id' => $team->id,
            'email' => 'invited@example.com',
            'invited_by' => $owner->id,
        ]);

        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'invited@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'invitation' => $invitation->code,
        ]);

        $this->assertAuthenticated();

        $response->assertRedirect(route('dashboard'));

        $user = User::where('email', 'invited@example.com')->firstOrFail();

        $this->assertTrue($user->belongsToTeam($team));
        $this->assertEquals($team->id, $user->fresh()->current_team_id);
        $this->assertNotNull($invitation->fresh()->accepted_at);

        // The whole point of invitation-only signup: no tenant of their own.
        $this->assertNull($user->personalTeam());
    }

    public function test_registration_without_an_invitation_is_rejected()
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('invitation');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
        $this->assertDatabaseMissing('teams', ['is_personal' => true, 'name' => "Test User's Team"]);
    }

    public function test_registration_with_an_expired_invitation_is_rejected()
    {
        $invitation = TeamInvitation::factory()->expired()->create([
            'email' => 'invited@example.com',
        ]);

        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'invited@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'invitation' => $invitation->code,
        ]);

        $response->assertSessionHasErrors('invitation');

        $this->assertGuest();
    }

    public function test_registration_is_rejected_when_the_email_does_not_match_the_invitation()
    {
        $invitation = TeamInvitation::factory()->create([
            'email' => 'someone-else@example.com',
        ]);

        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'invited@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'invitation' => $invitation->code,
        ]);

        $response->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
