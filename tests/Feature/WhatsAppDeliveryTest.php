<?php

namespace Tests\Feature;

use App\Contracts\TeamEntitlementSource;
use App\Contracts\WhatsAppDriver;
use App\Data\OutgoingWhatsAppMessage;
use App\Exceptions\WhatsAppDeliveryFailed;
use App\Jobs\SendWhatsAppMessageJob;
use App\Models\Team;
use App\Support\Entitlements;
use App\Support\TenantContext;
use App\Support\WhatsApp\WhatsAppSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Tests\Support\FakeTeamEntitlementSource;
use Tests\TestCase;

/**
 * WhatsApp delivery basics (B-3, tugas 05 Nov).
 *
 * Every test fakes the HTTP layer, so the suite exercises the real request shape
 * without a Meta account, a verified number, or a spent conversation credit.
 */
class WhatsAppDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private FakeTeamEntitlementSource $plans;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'whatsapp.driver' => 'meta',
            'whatsapp.graph.base_url' => 'https://graph.example.test',
            'whatsapp.graph.version' => 'v21.0',
        ]);

        $this->plans = new FakeTeamEntitlementSource('plus');
        $this->app->instance(TeamEntitlementSource::class, $this->plans);
        $this->app->forgetInstance(Entitlements::class);

        Http::preventStrayRequests();
    }

    private function connectedTenant(): Team
    {
        $team = Team::factory()->create();
        $team->connectWhatsApp('109999999999999', 'EAAG-secret-token', 'WABA-1');

        return $team;
    }

    /**
     * A tenant the customer wrote to a moment ago.
     *
     * Meta's 24-hour window is open only after an inbound message, and the sender
     * enforces it — so a free-text send without this row is a template failure, not
     * a delivery test.
     */
    private function tenantInConversation(string $number = '628123456789'): Team
    {
        $team = $this->connectedTenant();

        $team->whatsappConversations()->create([
            'contact_number' => $number,
            'state' => 'new',
            'last_inbound_at' => now(),
        ]);

        return $team;
    }

    public function test_a_text_message_is_posted_to_the_tenants_own_number(): void
    {
        Http::fake(['graph.example.test/*' => Http::response(['messages' => [['id' => 'wamid.ABC']]])]);

        $team = $this->tenantInConversation('6281234567890');

        $messageId = app(WhatsAppSender::class)->sendFor($team, OutgoingWhatsAppMessage::text('0812-3456-7890', 'Halo, ini uji coba.'));

        $this->assertSame('wamid.ABC', $messageId);

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://graph.example.test/v21.0/109999999999999/messages'
                && $request->hasHeader('Authorization', 'Bearer EAAG-secret-token')
                && $request['messaging_product'] === 'whatsapp'
                && $request['to'] === '6281234567890'
                && $request['type'] === 'text'
                && $request['text']['body'] === 'Halo, ini uji coba.';
        });
    }

    public function test_sending_happens_inside_the_tenants_context(): void
    {
        $team = $this->tenantInConversation();
        $seenTeamId = null;

        Http::fake(function () use (&$seenTeamId) {
            $seenTeamId = app(TenantContext::class)->team()?->getKey();

            return Http::response(['messages' => [['id' => 'wamid.CTX']]]);
        });

        (new SendWhatsAppMessageJob($team->id, '08123456789', 'Halo dari antrian.'))->handle();

        $this->assertSame($team->id, $seenTeamId);
    }

    public function test_a_rejected_token_fails_permanently_instead_of_retrying(): void
    {
        Http::fake(['graph.example.test/*' => Http::response(['error' => ['message' => 'Invalid OAuth access token']], 401)]);

        $team = $this->tenantInConversation();

        try {
            app(WhatsAppSender::class)->sendFor($team, OutgoingWhatsAppMessage::text('08123456789', 'Halo.'));
            $this->fail('A 401 should have raised a delivery failure.');
        } catch (WhatsAppDeliveryFailed $exception) {
            $this->assertFalse($exception->retryable);
            $this->assertSame(401, $exception->status);
            $this->assertStringContainsString('Invalid OAuth access token', $exception->getMessage());
        }
    }

    public function test_a_server_error_is_worth_retrying(): void
    {
        Http::fake(['graph.example.test/*' => Http::response('upstream boom', 503)]);

        $team = $this->tenantInConversation();

        try {
            app(WhatsAppSender::class)->sendFor($team, OutgoingWhatsAppMessage::text('08123456789', 'Halo.'));
            $this->fail('A 503 should have raised a delivery failure.');
        } catch (WhatsAppDeliveryFailed $exception) {
            $this->assertTrue($exception->retryable);
        }
    }

    public function test_a_tenant_without_the_bot_feature_sends_nothing(): void
    {
        Http::fake();

        $team = $this->connectedTenant();
        $this->plans->planCode = 'basic';
        $this->app->forgetInstance(Entitlements::class);

        $this->assertNull(app(WhatsAppSender::class)->sendFor($team, OutgoingWhatsAppMessage::text('08123456789', 'Halo.')));

        Http::assertNothingSent();
    }

    public function test_a_tenant_without_credentials_sends_nothing(): void
    {
        Http::fake();

        $team = Team::factory()->create();

        $this->assertFalse($team->isWhatsAppConnected());
        $this->assertNull(app(WhatsAppSender::class)->sendFor($team, OutgoingWhatsAppMessage::text('08123456789', 'Halo.')));

        Http::assertNothingSent();
    }

    public function test_the_access_token_is_encrypted_at_rest(): void
    {
        $team = $this->connectedTenant();

        $stored = DB::table('teams')->where('id', $team->id)->value('whatsapp_access_token');

        $this->assertIsString($stored);
        $this->assertNotSame('EAAG-secret-token', $stored);
        $this->assertSame('EAAG-secret-token', $team->fresh()?->whatsapp_access_token);
    }

    public function test_disconnecting_wipes_the_stored_token(): void
    {
        $team = $this->connectedTenant();

        $team->disconnectWhatsApp();

        $this->assertNull($team->fresh()?->whatsapp_access_token);
        $this->assertFalse($team->fresh()?->isWhatsAppConnected());
        $this->assertNull(DB::table('teams')->where('id', $team->id)->value('whatsapp_access_token'));
    }

    public function test_the_log_driver_does_not_touch_the_network(): void
    {
        Http::fake();
        config(['whatsapp.driver' => 'log']);
        $this->app->forgetInstance(WhatsAppDriver::class);

        $team = $this->tenantInConversation();

        $this->assertNull(app(WhatsAppSender::class)->sendFor($team, OutgoingWhatsAppMessage::text('08123456789', 'Halo.')));

        Http::assertNothingSent();
    }

    public function test_recipient_numbers_are_normalised_to_international_digits(): void
    {
        $this->assertSame('6281234567890', OutgoingWhatsAppMessage::text('+62 812-3456-7890', 'Halo.')->to);
        $this->assertSame('6281234567890', OutgoingWhatsAppMessage::text('0812 3456 7890', 'Halo.')->to);
    }

    public function test_an_impossible_recipient_is_rejected_before_sending(): void
    {
        Http::fake();

        $this->expectException(InvalidArgumentException::class);

        OutgoingWhatsAppMessage::text('123', 'Halo.');
    }

    public function test_an_empty_message_is_rejected_before_sending(): void
    {
        $this->expectException(InvalidArgumentException::class);

        OutgoingWhatsAppMessage::text('08123456789', '   ');
    }
}
