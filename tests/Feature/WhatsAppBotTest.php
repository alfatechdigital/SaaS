<?php

namespace Tests\Feature;

use App\Contracts\TeamEntitlementSource;
use App\Enums\Feature;
use App\Enums\WhatsAppMessageDirection;
use App\Jobs\ReplyToWhatsAppMessageJob;
use App\Models\Team;
use App\Models\WhatsAppMessage;
use App\Support\Entitlements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\FakeTeamEntitlementSource;
use Tests\TestCase;

/**
 * The two-way WhatsApp bot (B-3, tugas 10 Nov).
 *
 * The `log` driver is used throughout: the conversation logic is what is under
 * test, and asserting on a log line is honest evidence that a reply was produced
 * without inventing an HTTP fake for every step.
 */
class WhatsAppBotTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE_NUMBER_ID = '109999999999999';

    private FakeTeamEntitlementSource $plans;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'whatsapp.driver' => 'log',
            'whatsapp.webhook.app_secret' => 'test-app-secret',
            'whatsapp.webhook.verify_token' => 'test-verify-token',
        ]);

        $this->plans = new FakeTeamEntitlementSource('plus');
        $this->app->instance(TeamEntitlementSource::class, $this->plans);
        $this->app->forgetInstance(Entitlements::class);
    }

    private function connectedTenant(): Team
    {
        $team = Team::factory()->create(['name' => 'Alfatech Studio']);
        $team->connectWhatsApp(self::PHONE_NUMBER_ID, 'EAAG-token', 'WABA-1');

        return $team;
    }

    private function inbound(Team $team, string $body, string $wamid, string $type = 'text'): WhatsAppMessage
    {
        return $team->whatsappMessages()->create([
            'direction' => WhatsAppMessageDirection::Inbound,
            'wamid' => $wamid,
            'from_number' => '6281234567890',
            'to_number' => '628111222333',
            'type' => $type,
            'body' => $type === 'text' ? $body : null,
            'received_at' => now(),
        ]);
    }

    private function deliver(Team $team, WhatsAppMessage $message): void
    {
        (new ReplyToWhatsAppMessageJob((int) $team->getKey(), (int) $message->getKey()))->handle();
    }

    /**
     * @return array<int, string>
     */
    private function outboundBodies(Team $team): array
    {
        return DB::table('whatsapp_messages')
            ->where('team_id', $team->id)
            ->where('direction', 'outbound')
            ->orderBy('id')
            ->pluck('body')
            ->all();
    }

    private function state(Team $team): ?string
    {
        return DB::table('whatsapp_conversations')->where('team_id', $team->id)->value('state');
    }

    public function test_the_first_message_is_greeted_and_asks_for_a_name(): void
    {
        $team = $this->connectedTenant();

        $this->deliver($team, $this->inbound($team, 'Halo', 'wamid.1'));

        $bodies = $this->outboundBodies($team);

        $this->assertCount(1, $bodies);
        $this->assertStringContainsString('Terima kasih sudah menghubungi Alfatech Studio', $bodies[0]);
        $this->assertStringContainsString('Boleh tahu nama Anda?', $bodies[0]);
        $this->assertSame('awaiting_name', $this->state($team));
    }

    public function test_the_conversation_becomes_a_lead_with_the_collected_details(): void
    {
        $team = $this->connectedTenant();

        $this->deliver($team, $this->inbound($team, 'Halo', 'wamid.1'));
        $this->deliver($team, $this->inbound($team, 'Budi Santoso', 'wamid.2'));
        $this->deliver($team, $this->inbound($team, 'PT Maju Jaya', 'wamid.3'));
        $this->deliver($team, $this->inbound($team, 'Butuh website company profile', 'wamid.4'));

        $lead = DB::table('leads')->where('team_id', $team->id)->first();

        $this->assertNotNull($lead);
        $this->assertSame('PT Maju Jaya', $lead->company_name);
        $this->assertSame('Budi Santoso', $lead->contact_name);
        $this->assertSame('6281234567890', $lead->phone);
        $this->assertSame('whatsapp', $lead->source);
        $this->assertSame('Butuh website company profile', $lead->potential_project);
        $this->assertSame('new', $lead->status);
        $this->assertSame('Besok', $lead->next_follow_up);

        $conversation = DB::table('whatsapp_conversations')->where('team_id', $team->id)->first();

        $this->assertSame('completed', $conversation->state);
        $this->assertSame((int) $lead->id, (int) $conversation->lead_id);

        $bodies = $this->outboundBodies($team);
        $this->assertCount(4, $bodies);
        $this->assertStringContainsString('Tim Alfatech Studio akan menghubungi Anda', (string) end($bodies));
    }

    public function test_a_completed_conversation_receives_no_further_automatic_replies(): void
    {
        $team = $this->connectedTenant();

        $this->deliver($team, $this->inbound($team, 'Halo', 'wamid.1'));
        $this->deliver($team, $this->inbound($team, 'Budi', 'wamid.2'));
        $this->deliver($team, $this->inbound($team, 'PT Maju Jaya', 'wamid.3'));
        $this->deliver($team, $this->inbound($team, 'Butuh website', 'wamid.4'));

        $this->deliver($team, $this->inbound($team, 'Terima kasih', 'wamid.5'));

        $this->assertCount(4, $this->outboundBodies($team));
        $this->assertSame('completed', $this->state($team));
    }

    public function test_a_handed_over_conversation_stays_silent(): void
    {
        $team = $this->connectedTenant();

        $this->deliver($team, $this->inbound($team, 'Halo', 'wamid.1'));
        DB::table('whatsapp_conversations')->where('team_id', $team->id)->update(['state' => 'handed_over']);

        $this->deliver($team, $this->inbound($team, 'Saya mau bicara dengan orang', 'wamid.2'));

        $this->assertCount(1, $this->outboundBodies($team));
    }

    public function test_a_suspended_tenant_gets_no_automated_reply(): void
    {
        $team = $this->connectedTenant();
        $team->update(['suspended_at' => now()]);

        $this->deliver($team, $this->inbound($team, 'Halo', 'wamid.1'));

        $this->assertSame([], $this->outboundBodies($team));
        $this->assertSame(0, DB::table('whatsapp_conversations')->count());
    }

    public function test_a_non_text_message_is_explained_without_advancing_the_conversation(): void
    {
        $team = $this->connectedTenant();

        $this->deliver($team, $this->inbound($team, '', 'wamid.1', type: 'image'));

        $bodies = $this->outboundBodies($team);

        $this->assertCount(1, $bodies);
        $this->assertStringContainsString('hanya bisa membaca pesan teks', $bodies[0]);
        $this->assertSame('new', $this->state($team));
    }

    public function test_a_plan_without_lead_management_gets_the_conversation_but_no_lead(): void
    {
        $this->plans->planCode = 'basic';
        $this->plans->addonFeatures = [Feature::WhatsAppBot];
        $this->app->forgetInstance(Entitlements::class);

        $team = $this->connectedTenant();

        $this->deliver($team, $this->inbound($team, 'Halo', 'wamid.1'));
        $this->deliver($team, $this->inbound($team, 'Budi', 'wamid.2'));
        $this->deliver($team, $this->inbound($team, 'PT Maju Jaya', 'wamid.3'));
        $this->deliver($team, $this->inbound($team, 'Butuh website', 'wamid.4'));

        $this->assertSame('completed', $this->state($team));
        $this->assertSame(0, DB::table('leads')->count());
        $this->assertCount(4, $this->outboundBodies($team));
    }

    public function test_a_retried_job_does_not_reply_twice(): void
    {
        $team = $this->connectedTenant();
        $message = $this->inbound($team, 'Halo', 'wamid.1');

        $this->deliver($team, $message);
        $this->deliver($team, $message);

        $this->assertCount(1, $this->outboundBodies($team));
        $this->assertNotNull(DB::table('whatsapp_messages')->where('id', $message->id)->value('processed_at'));
    }

    public function test_the_webhook_starts_the_conversation(): void
    {
        $this->connectedTenant();

        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => 'WABA-1',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => ['display_phone_number' => '628111222333', 'phone_number_id' => self::PHONE_NUMBER_ID],
                        'messages' => [[
                            'from' => '6281234567890',
                            'id' => 'wamid.WEBHOOK-1',
                            'timestamp' => '1762771200',
                            'type' => 'text',
                            'text' => ['body' => 'Halo, saya mau tanya'],
                        ]],
                    ],
                ]],
            ]],
        ];

        $json = json_encode($payload, JSON_THROW_ON_ERROR);

        $this->call('POST', '/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $json, 'test-app-secret'),
        ], $json)->assertOk();

        $this->assertCount(1, DB::table('whatsapp_messages')->where('direction', 'outbound')->get());
        $this->assertSame('awaiting_name', DB::table('whatsapp_conversations')->value('state'));
    }
}
