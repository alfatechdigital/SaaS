<?php

namespace Tests\Feature;

use App\Contracts\TeamEntitlementSource;
use App\Data\OutgoingWhatsAppMessage;
use App\Enums\WhatsAppMessageDirection;
use App\Models\Team;
use App\Support\Entitlements;
use App\Support\WhatsApp\WhatsAppOptOut;
use App\Support\WhatsApp\WhatsAppSender;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Support\FakeTeamEntitlementSource;
use Tests\TestCase;

/**
 * Opt-out handling and delivery logging (B-3, tugas 11 Nov).
 *
 * Requests are signed exactly the way Meta signs them, so the whole webhook path
 * is exercised rather than the processor alone.
 */
class WhatsAppOptOutAndStatusTest extends TestCase
{
    use RefreshDatabase;

    private const APP_SECRET = 'test-app-secret';

    private const PHONE_NUMBER_ID = '109999999999999';

    private const CONTACT = '6281234567890';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'whatsapp.driver' => 'log',
            'whatsapp.webhook.app_secret' => self::APP_SECRET,
            'whatsapp.webhook.verify_token' => 'test-verify-token',
        ]);

        $this->app->instance(TeamEntitlementSource::class, new FakeTeamEntitlementSource('plus'));
        $this->app->forgetInstance(Entitlements::class);
    }

    private function tenant(): Team
    {
        $team = Team::factory()->create();
        $team->connectWhatsApp(self::PHONE_NUMBER_ID, 'EAAG-token', 'WABA-1');

        return $team;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function deliver(array $payload): TestResponse
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR);

        return $this->call('POST', '/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $json, self::APP_SECRET),
        ], $json);
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @return array<string, mixed>
     */
    private function inboundPayload(array $messages): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'WABA-1',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => ['display_phone_number' => '628111222333', 'phone_number_id' => self::PHONE_NUMBER_ID],
                        'messages' => $messages,
                    ],
                ]],
            ]],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $statuses
     * @return array<string, mixed>
     */
    private function statusPayload(array $statuses): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'WABA-1',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => ['display_phone_number' => '628111222333', 'phone_number_id' => self::PHONE_NUMBER_ID],
                        'statuses' => $statuses,
                    ],
                ]],
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function textMessage(string $body, string $wamid = 'wamid.IN-1'): array
    {
        return [
            'from' => self::CONTACT,
            'id' => $wamid,
            'timestamp' => '1762771200',
            'type' => 'text',
            'text' => ['body' => $body],
        ];
    }

    // --- opt-out ---------------------------------------------------------

    public function test_opt_out_keywords_are_matched_only_as_a_whole_message(): void
    {
        foreach (['STOP', 'stop', 'Berhenti', 'berhenti.', 'UNSUBSCRIBE', 'stop promo', 'Batal!'] as $keyword) {
            $this->assertTrue(WhatsAppOptOut::matches($keyword), "[{$keyword}] should opt out.");
        }

        foreach ([
            'tolong jangan stop layanan saya',
            'saya mau berhenti berlangganan besok',
            'halo',
            '',
            null,
        ] as $sentence) {
            $this->assertFalse(WhatsAppOptOut::matches($sentence), '['.var_export($sentence, true).'] should not opt out.');
        }
    }

    public function test_an_opt_out_message_stops_the_bot_and_is_recorded(): void
    {
        $team = $this->tenant();

        $this->deliver($this->inboundPayload([$this->textMessage('BERHENTI')]))->assertOk();

        $conversation = DB::table('whatsapp_conversations')->where('team_id', $team->id)->first();

        $this->assertNotNull($conversation);
        $this->assertNotNull($conversation->opted_out_at);
        $this->assertSame('handed_over', $conversation->state);

        // No automated reply at all: the customer asked for silence.
        $this->assertSame(0, DB::table('whatsapp_messages')->where('direction', 'outbound')->count());
    }

    public function test_an_opted_out_contact_is_never_messaged_again(): void
    {
        $team = $this->tenant();

        $team->whatsappConversations()->create([
            'contact_number' => self::CONTACT,
            'state' => 'handed_over',
            'last_inbound_at' => now(),
            'opted_out_at' => now(),
        ]);

        $this->assertNull(app(WhatsAppSender::class)->sendFor(
            $team,
            OutgoingWhatsAppMessage::text(self::CONTACT, 'Promo spesial'),
        ));
    }

    public function test_a_regular_message_still_gets_an_automated_reply(): void
    {
        $team = $this->tenant();

        $this->deliver($this->inboundPayload([$this->textMessage('Halo, mau tanya')]))->assertOk();

        $this->assertSame(1, DB::table('whatsapp_messages')->where('team_id', $team->id)->where('direction', 'outbound')->count());
    }

    // --- delivery log ----------------------------------------------------

    private function outboundWithWamid(Team $team, string $wamid): void
    {
        $team->whatsappMessages()->create([
            'direction' => WhatsAppMessageDirection::Outbound,
            'wamid' => $wamid,
            'from_number' => '628111222333',
            'to_number' => self::CONTACT,
            'type' => 'text',
            'body' => 'Halo.',
            'status' => 'sent',
        ]);
    }

    public function test_a_delivered_callback_records_the_delivery_time(): void
    {
        $team = $this->tenant();

        $this->deliver($this->inboundPayload([$this->textMessage('Halo')]))->assertOk();
        $this->outboundWithWamid($team, 'wamid.OUT-1');

        $this->deliver($this->statusPayload([[
            'id' => 'wamid.OUT-1',
            'status' => 'delivered',
            'timestamp' => '1762771300',
            'recipient_id' => self::CONTACT,
        ]]))->assertOk();

        $row = DB::table('whatsapp_messages')->where('wamid', 'wamid.OUT-1')->first();

        $this->assertSame('delivered', $row->status);
        $this->assertSame(
            1762771300,
            CarbonImmutable::parse($row->delivered_at, config('app.timezone'))->getTimestamp(),
        );
    }

    public function test_a_read_callback_does_not_move_backwards_when_delivered_arrives_late(): void
    {
        $team = $this->tenant();
        $this->outboundWithWamid($team, 'wamid.OUT-2');

        $this->deliver($this->statusPayload([['id' => 'wamid.OUT-2', 'status' => 'read', 'timestamp' => '1762771400']]))->assertOk();
        $this->deliver($this->statusPayload([['id' => 'wamid.OUT-2', 'status' => 'delivered', 'timestamp' => '1762771500']]))->assertOk();

        $row = DB::table('whatsapp_messages')->where('wamid', 'wamid.OUT-2')->first();

        $this->assertSame('read', $row->status);
    }

    public function test_a_failed_callback_keeps_the_provider_reason(): void
    {
        $team = $this->tenant();
        $this->outboundWithWamid($team, 'wamid.OUT-3');

        $this->deliver($this->statusPayload([[
            'id' => 'wamid.OUT-3',
            'status' => 'failed',
            'timestamp' => '1762771600',
            'errors' => [[
                'code' => 131026,
                'title' => 'Message undeliverable',
                'error_data' => ['details' => 'Recipient is not a WhatsApp user'],
            ]],
        ]]))->assertOk();

        $row = DB::table('whatsapp_messages')->where('wamid', 'wamid.OUT-3')->first();

        $this->assertSame('failed', $row->status);
        $this->assertSame('Message undeliverable — Recipient is not a WhatsApp user', $row->failure_reason);
        $this->assertNotNull($row->failed_at);
    }

    public function test_a_status_for_an_unknown_message_is_ignored(): void
    {
        $team = $this->tenant();

        $this->deliver($this->statusPayload([['id' => 'wamid.SOMEONE-ELSE', 'status' => 'delivered', 'timestamp' => '1762771300']]))
            ->assertOk();

        $this->assertSame(0, DB::table('whatsapp_messages')->where('wamid', 'wamid.SOMEONE-ELSE')->count());
    }

    public function test_a_status_cannot_touch_another_tenants_message(): void
    {
        $owner = $this->tenant();
        $this->outboundWithWamid($owner, 'wamid.SHARED');

        $other = Team::factory()->create();
        $other->connectWhatsApp('105550000000000', 'EAAG-two', 'WABA-2');

        $payload = $this->statusPayload([['id' => 'wamid.SHARED', 'status' => 'read', 'timestamp' => '1762771400']]);
        $payload['entry'][0]['changes'][0]['value']['metadata']['phone_number_id'] = '105550000000000';

        $this->deliver($payload)->assertOk();

        $this->assertSame('sent', DB::table('whatsapp_messages')->where('wamid', 'wamid.SHARED')->value('status'));
    }
}
