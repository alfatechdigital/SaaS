<?php

namespace Tests\Feature;

use App\Contracts\TeamEntitlementSource;
use App\Models\Team;
use App\Models\WhatsAppMessage;
use App\Support\Entitlements;
use App\Support\TenantContext;
use App\Support\WhatsApp\WhatsAppQuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\Support\FakeTeamEntitlementSource;
use Tests\TestCase;

/**
 * WhatsApp end to end (B-3, tugas 12 Nov): a signed webhook arrives, the bot
 * replies through the tenant's own number, Meta's status callback closes the
 * loop, and nothing in that chain crosses a tenant boundary.
 *
 * Deliberately built from the outside in: only the provider's HTTP endpoint is
 * faked, so middleware, routing, tenant context, queue and delivery log all run
 * for real.
 */
class WhatsAppEndToEndTest extends TestCase
{
    use RefreshDatabase;

    private const APP_SECRET = 'test-app-secret';

    private const CONTACT = '6281234567890';

    private FakeTeamEntitlementSource $plans;

    private int $outboundSequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'whatsapp.driver' => 'meta',
            'whatsapp.graph.base_url' => 'https://graph.example.test',
            'whatsapp.graph.version' => 'v21.0',
            'whatsapp.webhook.app_secret' => self::APP_SECRET,
            'whatsapp.webhook.verify_token' => 'test-verify-token',
            'whatsapp.rate_limit.per_minute' => 30,
            'whatsapp.service_window.hours' => 24,
            'whatsapp.quota.addon_allowance' => 500,
        ]);

        $this->plans = new FakeTeamEntitlementSource('plus');
        $this->app->instance(TeamEntitlementSource::class, $this->plans);
        $this->app->forgetInstance(Entitlements::class);

        Http::preventStrayRequests();
    }

    private function tenant(string $phoneNumberId, string $token, string $businessAccountId, string $name): Team
    {
        $team = Team::factory()->create(['name' => $name]);
        $team->connectWhatsApp($phoneNumberId, $token, $businessAccountId);

        return $team;
    }

    /**
     * Every outbound call gets a fresh provider id, so status callbacks can be
     * matched back to the reply that produced them.
     */
    private function fakeProvider(): void
    {
        Http::fake(function () {
            $this->outboundSequence++;

            return Http::response(['messages' => [['id' => 'wamid.OUT-'.$this->outboundSequence]]]);
        });
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
    private function inboundPayload(Team $team, array $messages): array
    {
        return $this->payload($team, ['messages' => $messages]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $statuses
     * @return array<string, mixed>
     */
    private function statusPayload(Team $team, array $statuses): array
    {
        return $this->payload($team, ['statuses' => $statuses]);
    }

    /**
     * @param  array<string, mixed>  $value
     * @return array<string, mixed>
     */
    private function payload(Team $team, array $value): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => $team->whatsapp_business_account_id,
                'changes' => [[
                    'field' => 'messages',
                    'value' => $value + [
                        'messaging_product' => 'whatsapp',
                        'metadata' => [
                            'display_phone_number' => '628111222333',
                            'phone_number_id' => $team->whatsapp_phone_number_id,
                        ],
                    ],
                ]],
            ]],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function text(string $body, string $wamid): array
    {
        return [
            'from' => self::CONTACT,
            'id' => $wamid,
            'timestamp' => '1762771200',
            'type' => 'text',
            'text' => ['body' => $body],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function bodies(Team $team, string $direction): array
    {
        return DB::table('whatsapp_messages')
            ->where('team_id', $team->id)
            ->where('direction', $direction)
            ->orderBy('id')
            ->pluck('body')
            ->all();
    }

    public function test_a_customer_message_is_answered_through_the_tenants_own_number_and_closes_the_loop(): void
    {
        $this->fakeProvider();
        $team = $this->tenant('109999999999999', 'EAAG-token-a', 'WABA-A', 'Alfatech Studio');

        $this->deliver($this->inboundPayload($team, [$this->text('Halo, mau tanya', 'wamid.IN-1')]))->assertOk();

        $outbound = DB::table('whatsapp_messages')
            ->where('team_id', $team->id)
            ->where('direction', 'outbound')
            ->first();

        $this->assertNotNull($outbound);
        $this->assertSame('wamid.OUT-1', $outbound->wamid);
        $this->assertSame('sent', $outbound->status);
        $this->assertSame(self::CONTACT, $outbound->to_number);

        Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://graph.example.test/v21.0/109999999999999/messages'
            && $request->hasHeader('Authorization', 'Bearer EAAG-token-a')
            && $request['to'] === self::CONTACT
            && $request['type'] === 'text');

        $this->assertSame('awaiting_name', DB::table('whatsapp_conversations')->where('team_id', $team->id)->value('state'));
        $this->assertSame(1, DB::table('usage_counters')->where('team_id', $team->id)->value('used'));

        $this->deliver($this->statusPayload($team, [
            ['id' => 'wamid.OUT-1', 'status' => 'delivered', 'timestamp' => '1762771300'],
        ]))->assertOk();
        $this->deliver($this->statusPayload($team, [
            ['id' => 'wamid.OUT-1', 'status' => 'read', 'timestamp' => '1762771400'],
        ]))->assertOk();

        $delivered = DB::table('whatsapp_messages')->where('wamid', 'wamid.OUT-1')->first();

        $this->assertSame('read', $delivered->status);
        $this->assertNotNull($delivered->delivered_at);
        $this->assertNotNull($delivered->read_at);
    }

    public function test_the_whole_conversation_becomes_a_lead_and_every_reply_is_tracked(): void
    {
        $this->fakeProvider();
        $team = $this->tenant('109999999999999', 'EAAG-token-a', 'WABA-A', 'Alfatech Studio');

        $steps = [
            ['Halo, mau tanya', 'wamid.IN-1'],
            ['Budi', 'wamid.IN-2'],
            ['CV Maju Jaya', 'wamid.IN-3'],
            ['Butuh website company profile', 'wamid.IN-4'],
        ];

        foreach ($steps as [$body, $wamid]) {
            $this->deliver($this->inboundPayload($team, [$this->text($body, $wamid)]))->assertOk();
        }

        $conversation = DB::table('whatsapp_conversations')->where('team_id', $team->id)->first();

        $this->assertSame('completed', $conversation->state);
        $this->assertSame('Budi', $conversation->contact_name);
        $this->assertSame('CV Maju Jaya', $conversation->company_name);
        $this->assertNotNull($conversation->lead_id);

        $lead = DB::table('leads')->where('id', $conversation->lead_id)->first();

        $this->assertSame('whatsapp', $lead->source);
        $this->assertSame(self::CONTACT, $lead->phone);
        $this->assertSame('Butuh website company profile', $lead->potential_project);

        $this->assertCount(4, $this->bodies($team, 'outbound'));
        $this->assertSame(4, DB::table('usage_counters')->where('team_id', $team->id)->value('used'));

        $this->deliver($this->statusPayload($team, [
            ['id' => 'wamid.OUT-1', 'status' => 'delivered', 'timestamp' => '1762771300'],
            ['id' => 'wamid.OUT-2', 'status' => 'delivered', 'timestamp' => '1762771310'],
            ['id' => 'wamid.OUT-3', 'status' => 'delivered', 'timestamp' => '1762771320'],
            ['id' => 'wamid.OUT-4', 'status' => 'delivered', 'timestamp' => '1762771330'],
        ]))->assertOk();

        $this->assertSame(4, DB::table('whatsapp_messages')
            ->where('team_id', $team->id)
            ->where('direction', 'outbound')
            ->where('status', 'delivered')
            ->count());
    }

    public function test_each_tenant_only_ever_sees_and_answers_its_own_conversation(): void
    {
        $this->fakeProvider();
        $first = $this->tenant('109999999999999', 'EAAG-token-a', 'WABA-A', 'Alfatech Studio');
        $second = $this->tenant('105550000000000', 'EAAG-token-b', 'WABA-B', 'Mitra Digital');

        $this->deliver($this->inboundPayload($first, [$this->text('Halo tenant A', 'wamid.IN-A')]))->assertOk();
        $this->deliver($this->inboundPayload($second, [$this->text('Halo tenant B', 'wamid.IN-B')]))->assertOk();

        $this->assertSame(['Halo tenant A'], $this->bodies($first, 'inbound'));
        $this->assertSame(['Halo tenant B'], $this->bodies($second, 'inbound'));
        $this->assertCount(1, $this->bodies($first, 'outbound'));
        $this->assertCount(1, $this->bodies($second, 'outbound'));

        $this->assertSame(1, DB::table('whatsapp_conversations')->where('team_id', $first->id)->count());
        $this->assertSame(1, DB::table('whatsapp_conversations')->where('team_id', $second->id)->count());

        $visibleToFirst = app(TenantContext::class)->runFor(
            $first,
            fn (): array => WhatsAppMessage::query()->pluck('team_id')->unique()->values()->all(),
        );

        $this->assertSame([$first->id], array_values($visibleToFirst));

        Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://graph.example.test/v21.0/109999999999999/messages'
            && $request->hasHeader('Authorization', 'Bearer EAAG-token-a'));
        Http::assertSent(fn (ClientRequest $request): bool => $request->url() === 'https://graph.example.test/v21.0/105550000000000/messages'
            && $request->hasHeader('Authorization', 'Bearer EAAG-token-b'));

        // A delivery callback for the first tenant must not touch the second's
        // reply, even though both conversations belong to the same contact.
        $this->deliver($this->statusPayload($first, [
            ['id' => 'wamid.OUT-1', 'status' => 'read', 'timestamp' => '1762771400'],
        ]))->assertOk();

        $this->assertSame('read', DB::table('whatsapp_messages')->where('wamid', 'wamid.OUT-1')->value('status'));
        $this->assertSame('sent', DB::table('whatsapp_messages')->where('wamid', 'wamid.OUT-2')->value('status'));
    }

    public function test_a_replayed_webhook_does_not_answer_the_customer_twice(): void
    {
        $this->fakeProvider();
        $team = $this->tenant('109999999999999', 'EAAG-token-a', 'WABA-A', 'Alfatech Studio');

        $payload = $this->inboundPayload($team, [$this->text('Halo', 'wamid.IN-1')]);

        $this->deliver($payload)->assertOk();
        $this->deliver($payload)->assertOk();

        $this->assertCount(1, $this->bodies($team, 'inbound'));
        $this->assertCount(1, $this->bodies($team, 'outbound'));
        $this->assertSame(1, DB::table('usage_counters')->where('team_id', $team->id)->value('used'));

        Http::assertSentCount(1);
    }

    public function test_an_exhausted_quota_leaves_the_conversation_untouched_and_records_nothing(): void
    {
        $this->fakeProvider();
        $team = $this->tenant('109999999999999', 'EAAG-token-a', 'WABA-A', 'Alfatech Studio');

        $team->usageCounters()->create([
            'metric' => WhatsAppQuota::METRIC,
            'period' => now()->format('Y-m'),
            'used' => 1000,
            'limit' => 1000,
        ]);

        $this->deliver($this->inboundPayload($team, [$this->text('Halo', 'wamid.IN-1')]))->assertOk();

        $this->assertCount(0, $this->bodies($team, 'outbound'));
        $this->assertSame('new', DB::table('whatsapp_conversations')->where('team_id', $team->id)->value('state'));
        $this->assertSame(1000, DB::table('usage_counters')->where('team_id', $team->id)->value('used'));

        Http::assertNothingSent();
    }
}
