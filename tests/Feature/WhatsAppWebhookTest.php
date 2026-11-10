<?php

namespace Tests\Feature;

use App\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Inbound WhatsApp webhook (B-3, tugas 09 Nov).
 *
 * Every request is signed exactly the way Meta signs it — HMAC-SHA256 over the raw
 * body — so the middleware is exercised for real instead of being mocked away.
 */
class WhatsAppWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const APP_SECRET = 'test-app-secret';

    private const VERIFY_TOKEN = 'test-verify-token';

    private const PHONE_NUMBER_ID = '109999999999999';

    private const WABA_ID = 'WABA-1';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'whatsapp.webhook.app_secret' => self::APP_SECRET,
            'whatsapp.webhook.verify_token' => self::VERIFY_TOKEN,
        ]);
    }

    private function connectedTenant(): Team
    {
        $team = Team::factory()->create();
        $team->connectWhatsApp(self::PHONE_NUMBER_ID, 'EAAG-token', self::WABA_ID);

        return $team;
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @return array<string, mixed>
     */
    private function payload(array $messages, string $phoneNumberId = self::PHONE_NUMBER_ID, string $wabaId = self::WABA_ID): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => $wabaId,
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => [
                            'display_phone_number' => '628111222333',
                            'phone_number_id' => $phoneNumberId,
                        ],
                        'messages' => $messages,
                    ],
                ]],
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function textMessage(string $wamid = 'wamid.IN-1', string $from = '6281234567890', string $body = 'Halo, saya mau tanya harga.'): array
    {
        return [
            'from' => $from,
            'id' => $wamid,
            'timestamp' => '1762771200',
            'type' => 'text',
            'text' => ['body' => $body],
        ];
    }

    /**
     * Post the payload exactly as Meta does: JSON body plus a signature over it.
     *
     * @param  array<string, mixed>  $payload
     */
    private function postSigned(array $payload, ?string $secret = self::APP_SECRET, ?string $signature = null): TestResponse
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature ??= 'sha256='.hash_hmac('sha256', $json, (string) $secret);

        $server = ['CONTENT_TYPE' => 'application/json'];

        if ($signature !== '') {
            $server['HTTP_X_HUB_SIGNATURE_256'] = $signature;
        }

        return $this->call('POST', '/webhooks/whatsapp', [], [], [], $server, $json);
    }

    public function test_the_subscription_handshake_echoes_the_challenge(): void
    {
        $response = $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token='.self::VERIFY_TOKEN.'&hub.challenge=1158201444');

        $response->assertOk();
        $this->assertSame('1158201444', $response->getContent());
    }

    public function test_the_handshake_is_refused_with_a_wrong_token(): void
    {
        $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=nope&hub.challenge=1158201444')
            ->assertForbidden();
    }

    public function test_the_handshake_is_refused_when_no_verify_token_is_configured(): void
    {
        config(['whatsapp.webhook.verify_token' => null]);

        $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=&hub.challenge=1')
            ->assertForbidden();
    }

    public function test_a_signed_message_is_stored_for_the_matching_tenant(): void
    {
        $team = $this->connectedTenant();

        $this->postSigned($this->payload([$this->textMessage()]))->assertOk();

        $row = DB::table('whatsapp_messages')->first();

        $this->assertNotNull($row);
        $this->assertSame($team->id, (int) $row->team_id);
        $this->assertSame('inbound', $row->direction);
        $this->assertSame('wamid.IN-1', $row->wamid);
        $this->assertSame('6281234567890', $row->from_number);
        $this->assertSame('628111222333', $row->to_number);
        $this->assertSame('text', $row->type);
        $this->assertSame('Halo, saya mau tanya harga.', $row->body);
        $this->assertSame('2025-11-10 10:40:00', $row->received_at);
    }

    public function test_a_message_from_another_tenant_is_never_mixed_in(): void
    {
        $connected = $this->connectedTenant();
        $other = Team::factory()->create();
        $other->connectWhatsApp('105550000000000', 'EAAG-other', 'WABA-2');

        $this->postSigned($this->payload([$this->textMessage()]))->assertOk();

        $this->assertSame(1, DB::table('whatsapp_messages')->count());
        $this->assertSame(1, DB::table('whatsapp_messages')->where('team_id', $connected->id)->count());
        $this->assertSame(0, DB::table('whatsapp_messages')->where('team_id', $other->id)->count());
    }

    public function test_an_unsigned_request_is_refused_and_stores_nothing(): void
    {
        $this->connectedTenant();

        $this->postSigned($this->payload([$this->textMessage()]), signature: '')->assertForbidden();

        $this->assertSame(0, DB::table('whatsapp_messages')->count());
    }

    public function test_a_forged_signature_is_refused_and_stores_nothing(): void
    {
        $this->connectedTenant();

        $this->postSigned($this->payload([$this->textMessage()]), signature: 'sha256='.str_repeat('a', 64))
            ->assertForbidden();

        $this->assertSame(0, DB::table('whatsapp_messages')->count());
    }

    public function test_a_request_signed_with_the_wrong_secret_is_refused(): void
    {
        $this->connectedTenant();

        $this->postSigned($this->payload([$this->textMessage()]), secret: 'guessed-secret')->assertForbidden();

        $this->assertSame(0, DB::table('whatsapp_messages')->count());
    }

    public function test_nothing_is_accepted_when_no_app_secret_is_configured(): void
    {
        $this->connectedTenant();
        config(['whatsapp.webhook.app_secret' => null]);

        $this->postSigned($this->payload([$this->textMessage()]))->assertForbidden();

        $this->assertSame(0, DB::table('whatsapp_messages')->count());
    }

    public function test_a_retry_of_the_same_message_is_stored_once(): void
    {
        $this->connectedTenant();
        $payload = $this->payload([$this->textMessage()]);

        $this->postSigned($payload)->assertOk();
        $this->postSigned($payload)->assertOk();

        $this->assertSame(1, DB::table('whatsapp_messages')->count());
    }

    public function test_several_messages_in_one_batch_are_all_stored(): void
    {
        $this->connectedTenant();

        $this->postSigned($this->payload([
            $this->textMessage('wamid.IN-1', body: 'Pertama'),
            $this->textMessage('wamid.IN-2', body: 'Kedua'),
        ]))->assertOk();

        $this->assertSame(2, DB::table('whatsapp_messages')->count());
    }

    public function test_a_message_for_an_unknown_number_is_acknowledged_but_not_stored(): void
    {
        $this->connectedTenant();

        $this->postSigned($this->payload([$this->textMessage()], phoneNumberId: '100000000000000'))
            ->assertOk();

        $this->assertSame(0, DB::table('whatsapp_messages')->count());
    }

    public function test_a_webhook_for_a_different_business_account_is_not_stored(): void
    {
        $this->connectedTenant();

        $this->postSigned($this->payload([$this->textMessage()], wabaId: 'WABA-SOMEONE-ELSE'))
            ->assertOk();

        $this->assertSame(0, DB::table('whatsapp_messages')->count());
    }

    public function test_delivery_status_callbacks_are_acknowledged_without_being_stored(): void
    {
        $this->connectedTenant();

        $payload = $this->payload([]);
        $payload['entry'][0]['changes'][0]['value']['statuses'] = [[
            'id' => 'wamid.OUT-1',
            'status' => 'delivered',
            'timestamp' => '1762771300',
            'recipient_id' => '6281234567890',
        ]];

        $this->postSigned($payload)->assertOk();

        $this->assertSame(0, DB::table('whatsapp_messages')->count());
    }

    public function test_a_non_json_body_with_a_valid_signature_is_rejected(): void
    {
        $body = 'not json';
        $signature = 'sha256='.hash_hmac('sha256', $body, self::APP_SECRET);

        $this->call('POST', '/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => $signature,
        ], $body)->assertStatus(400);
    }
}
