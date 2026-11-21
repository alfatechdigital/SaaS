<?php

namespace Tests\Feature;

use App\Enums\Feature;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Tenant-facing WhatsApp connection page (B-3, follow-up 12 Nov).
 *
 * The token is the one value here that must never leave the server again, so the
 * tests assert its absence from the Inertia payload and from the rendered HTML,
 * not only that it is encrypted in the database.
 */
class WhatsAppSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'EAAG-permanent-token-1234567890';

    /**
     * @return array{0: User, 1: Team}
     */
    private function member(TeamRole $role = TeamRole::Owner): array
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['name' => 'PT Alfatech Digital Solutions']);

        $team->members()->attach($user, ['role' => $role->value]);
        $user->switchTeam($team);

        return [$user, $team];
    }

    private function pageUrl(Team $team): string
    {
        return route('whatsapp.edit', ['current_team' => $team->slug]);
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $team = Team::factory()->create();

        $this->get($this->pageUrl($team))->assertRedirect(route('login'));
    }

    public function test_an_owner_sees_the_connection_status_without_the_token(): void
    {
        [$owner, $team] = $this->member();

        $response = $this->actingAs($owner)->get($this->pageUrl($team));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('admin/settings/WhatsApp')
            ->where('whatsapp.connected', false)
            ->where('whatsapp.has_token', false)
            ->where('whatsapp.phone_number_id', null)
            ->where('whatsapp.business_account_id', null)
            ->where('whatsapp.driver', config('whatsapp.driver'))
            ->where('whatsapp.entitled', $team->can(Feature::WhatsAppBot))
            ->where('whatsapp.webhook_url', url('/webhooks/whatsapp'))
            ->missing('whatsapp.access_token'));
    }

    public function test_an_owner_can_connect_the_tenants_number(): void
    {
        [$owner, $team] = $this->member();

        $this->actingAs($owner)
            ->put($this->pageUrl($team), [
                'phone_number_id' => '109999999999999',
                'business_account_id' => '102290129340398',
                'access_token' => self::TOKEN,
            ])
            ->assertRedirect();

        $team->refresh();

        $this->assertSame('109999999999999', $team->whatsapp_phone_number_id);
        $this->assertSame('102290129340398', $team->whatsapp_business_account_id);
        $this->assertTrue($team->isWhatsAppConnected());
        $this->assertNotNull($team->whatsapp_connected_at);
        $this->assertSame(self::TOKEN, $team->whatsapp_access_token);
        $this->assertNotSame(
            self::TOKEN,
            DB::table('teams')->where('id', $team->id)->value('whatsapp_access_token'),
        );
    }

    public function test_the_token_is_never_sent_back_to_the_browser(): void
    {
        [$owner, $team] = $this->member();
        $team->connectWhatsApp('109999999999999', self::TOKEN, '102290129340398');

        $response = $this->actingAs($owner)->get($this->pageUrl($team));

        $response->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('whatsapp.connected', true)
            ->where('whatsapp.has_token', true)
            ->missing('whatsapp.access_token'));

        $this->assertStringNotContainsString(self::TOKEN, $response->getContent());
    }

    public function test_the_shared_page_props_never_expose_the_token(): void
    {
        [$owner, $team] = $this->member();
        $team->connectWhatsApp('109999999999999', self::TOKEN, '102290129340398');

        // The token used to arrive through `auth.user.current_team`, which is a
        // shared Inertia prop on *every* page — not only this one.
        $response = $this->actingAs($owner)
            ->get(route('settings.index', ['current_team' => $team->slug]));

        $response->assertOk();
        $this->assertStringNotContainsString(self::TOKEN, $response->getContent());
    }

    public function test_saving_again_without_a_token_keeps_the_stored_one(): void
    {
        [$owner, $team] = $this->member();
        $team->connectWhatsApp('109999999999999', self::TOKEN, '102290129340398');

        $this->actingAs($owner)
            ->put($this->pageUrl($team), [
                'phone_number_id' => '105550000000000',
                'business_account_id' => '',
                'access_token' => '',
            ])
            ->assertRedirect();

        $team->refresh();

        $this->assertSame('105550000000000', $team->whatsapp_phone_number_id);
        $this->assertNull($team->whatsapp_business_account_id);
        $this->assertSame(self::TOKEN, $team->whatsapp_access_token);
        $this->assertTrue($team->isWhatsAppConnected());
    }

    public function test_a_token_is_required_for_the_first_connection(): void
    {
        [$owner, $team] = $this->member();

        $this->actingAs($owner)
            ->put($this->pageUrl($team), [
                'phone_number_id' => '109999999999999',
                'business_account_id' => '',
            ])
            ->assertSessionHasErrors('access_token');

        $this->assertFalse($team->fresh()?->isWhatsAppConnected());
    }

    public function test_the_number_id_must_be_numeric(): void
    {
        [$owner, $team] = $this->member();

        $this->actingAs($owner)
            ->put($this->pageUrl($team), [
                'phone_number_id' => 'not-a-number',
                'access_token' => self::TOKEN,
            ])
            ->assertSessionHasErrors('phone_number_id');

        $this->assertFalse($team->fresh()?->isWhatsAppConnected());
    }

    public function test_an_owner_can_disconnect_and_the_token_is_wiped(): void
    {
        [$owner, $team] = $this->member();
        $team->connectWhatsApp('109999999999999', self::TOKEN, '102290129340398');

        $this->actingAs($owner)
            ->delete($this->pageUrl($team))
            ->assertRedirect();

        $team->refresh();

        $this->assertNull($team->whatsapp_phone_number_id);
        $this->assertNull($team->whatsapp_business_account_id);
        $this->assertNull($team->whatsapp_access_token);
        $this->assertNull($team->whatsapp_connected_at);
        $this->assertFalse($team->isWhatsAppConnected());
    }

    public function test_credentials_can_be_prepared_before_the_plan_includes_the_bot(): void
    {
        [$owner, $team] = $this->member();

        $this->assertFalse($team->can(Feature::WhatsAppBot));

        $this->actingAs($owner)
            ->put($this->pageUrl($team), [
                'phone_number_id' => '109999999999999',
                'access_token' => self::TOKEN,
            ])
            ->assertRedirect();

        $this->assertTrue($team->fresh()?->isWhatsAppConnected());
    }

    public function test_a_regular_member_cannot_view_or_change_the_connection(): void
    {
        [$member, $team] = $this->member(TeamRole::Member);

        $this->actingAs($member)->get($this->pageUrl($team))->assertForbidden();

        $this->actingAs($member)
            ->put($this->pageUrl($team), [
                'phone_number_id' => '109999999999999',
                'access_token' => self::TOKEN,
            ])
            ->assertForbidden();

        $this->assertFalse($team->fresh()?->isWhatsAppConnected());
    }

    public function test_a_non_member_cannot_open_another_tenants_connection_page(): void
    {
        $outsider = User::factory()->create();
        [$admin, $team] = $this->member(TeamRole::Admin);

        $this->actingAs($admin)
            ->get($this->pageUrl($team))
            ->assertOk();

        $this->actingAs($outsider)
            ->get($this->pageUrl($team))
            ->assertForbidden();
    }

    public function test_another_tenants_owner_cannot_replace_this_number(): void
    {
        [, $team] = $this->member();
        [$intruder] = $this->member();

        $this->actingAs($intruder)
            ->put($this->pageUrl($team), [
                'phone_number_id' => '105550000000000',
                'access_token' => self::TOKEN,
            ])
            ->assertForbidden();

        $this->assertFalse($team->fresh()?->isWhatsAppConnected());
    }
}
