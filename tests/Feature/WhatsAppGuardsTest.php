<?php

namespace Tests\Feature;

use App\Contracts\TeamEntitlementSource;
use App\Contracts\WhatsAppDriver;
use App\Data\OutgoingWhatsAppMessage;
use App\Enums\Feature;
use App\Exceptions\TemplateRequired;
use App\Exceptions\WhatsAppDeliveryFailed;
use App\Exceptions\WhatsAppRateLimited;
use App\Models\Team;
use App\Support\Entitlements;
use App\Support\WhatsApp\WhatsAppQuota;
use App\Support\WhatsApp\WhatsAppSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Support\FakeTeamEntitlementSource;
use Tests\TestCase;

/**
 * WhatsApp abuse guards (B-3, tugas 11 Nov): monthly quota, rate limit, service
 * window and template sending.
 */
class WhatsAppGuardsTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE_NUMBER_ID = '109999999999999';

    private FakeTeamEntitlementSource $plans;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'whatsapp.driver' => 'meta',
            'whatsapp.graph.base_url' => 'https://graph.example.test',
            'whatsapp.graph.version' => 'v21.0',
            'whatsapp.rate_limit.per_minute' => 30,
            'whatsapp.service_window.hours' => 24,
            'whatsapp.template.language' => 'id',
            'whatsapp.quota.addon_allowance' => 500,
        ]);

        $this->plans = new FakeTeamEntitlementSource('plus');
        $this->app->instance(TeamEntitlementSource::class, $this->plans);
        $this->app->forgetInstance(Entitlements::class);

        Http::preventStrayRequests();
    }

    private function tenant(?string $lastInbound = 'now'): Team
    {
        $team = Team::factory()->create();
        $team->connectWhatsApp(self::PHONE_NUMBER_ID, 'EAAG-token', 'WABA-1');

        $team->whatsappConversations()->create([
            'contact_number' => '628123456789',
            'state' => 'new',
            'last_inbound_at' => $lastInbound === null ? null : now()->parse($lastInbound),
        ]);

        return $team;
    }

    private function counter(Team $team): ?object
    {
        return DB::table('usage_counters')
            ->where('team_id', $team->id)
            ->where('metric', WhatsAppQuota::METRIC)
            ->first();
    }

    public function test_the_monthly_quota_is_taken_from_the_plan(): void
    {
        $team = $this->tenant();

        $this->assertSame(1000, app(WhatsAppQuota::class)->limit($team));
        $this->assertSame(1000, app(WhatsAppQuota::class)->remaining($team));
    }

    public function test_each_sent_message_consumes_one_unit_of_quota(): void
    {
        Http::fake(['graph.example.test/*' => Http::response(['messages' => [['id' => 'wamid.1']]])]);
        $team = $this->tenant();

        app(WhatsAppSender::class)->sendFor($team, OutgoingWhatsAppMessage::text('08123456789', 'Halo.'));

        $this->assertSame(1, $this->counter($team)->used);
        $this->assertSame(999, app(WhatsAppQuota::class)->remaining($team));
    }

    public function test_an_exhausted_quota_stops_sending_without_spending_the_provider(): void
    {
        Http::fake();
        $team = $this->tenant();

        $team->usageCounters()->create([
            'metric' => WhatsAppQuota::METRIC,
            'period' => now()->format('Y-m'),
            'used' => 1000,
            'limit' => 1000,
        ]);

        $this->assertNull(app(WhatsAppSender::class)->sendFor($team, OutgoingWhatsAppMessage::text('08123456789', 'Halo.')));

        Http::assertNothingSent();
        $this->assertSame(0, app(WhatsAppQuota::class)->remaining($team));
        $this->assertSame(1000, $this->counter($team)->used);
    }

    public function test_a_failed_send_does_not_consume_quota(): void
    {
        Http::fake(['graph.example.test/*' => Http::response('boom', 503)]);
        $team = $this->tenant();

        try {
            app(WhatsAppSender::class)->sendFor($team, OutgoingWhatsAppMessage::text('08123456789', 'Halo.'));
        } catch (WhatsAppDeliveryFailed) {
            // expected
        }

        $this->assertSame(0, $this->counter($team)->used);
    }

    public function test_an_addon_tenant_gets_an_allowance_instead_of_zero(): void
    {
        $this->plans->planCode = 'basic';
        $this->plans->addonFeatures = [Feature::WhatsAppBot];
        $this->app->forgetInstance(Entitlements::class);

        $team = $this->tenant();

        $this->assertSame(500, app(WhatsAppQuota::class)->limit($team));
    }

    public function test_sending_faster_than_the_rate_limit_is_refused_with_a_retry_delay(): void
    {
        Http::fake(['graph.example.test/*' => Http::response(['messages' => [['id' => 'wamid.1']]])]);
        config(['whatsapp.rate_limit.per_minute' => 1]);

        $team = $this->tenant();

        app(WhatsAppSender::class)->sendFor($team, OutgoingWhatsAppMessage::text('08123456789', 'Satu.'));

        try {
            app(WhatsAppSender::class)->sendFor($team, OutgoingWhatsAppMessage::text('08123456789', 'Dua.'));
            $this->fail('The second message should have been rate limited.');
        } catch (WhatsAppRateLimited $exception) {
            $this->assertGreaterThan(0, $exception->retryAfter);
        }

        Http::assertSentCount(1);
    }

    public function test_the_rate_limit_is_per_tenant(): void
    {
        Http::fake(['graph.example.test/*' => Http::response(['messages' => [['id' => 'wamid.1']]])]);
        config(['whatsapp.rate_limit.per_minute' => 1]);

        $first = $this->tenant();
        $second = Team::factory()->create();
        $second->connectWhatsApp('105550000000000', 'EAAG-two', 'WABA-2');
        $second->whatsappConversations()->create([
            'contact_number' => '628123456789',
            'state' => 'new',
            'last_inbound_at' => now(),
        ]);

        app(WhatsAppSender::class)->sendFor($first, OutgoingWhatsAppMessage::text('08123456789', 'Satu.'));
        app(WhatsAppSender::class)->sendFor($second, OutgoingWhatsAppMessage::text('08123456789', 'Dua.'));

        Http::assertSentCount(2);
    }

    public function test_free_text_is_refused_outside_the_service_window(): void
    {
        Http::fake();
        $team = $this->tenant(lastInbound: '-25 hours');

        $this->expectException(TemplateRequired::class);

        app(WhatsAppSender::class)->sendFor($team, OutgoingWhatsAppMessage::text('08123456789', 'Halo.'));
    }

    public function test_free_text_is_refused_for_a_contact_that_never_wrote(): void
    {
        Http::fake();
        $team = $this->tenant(lastInbound: null);

        $this->expectException(TemplateRequired::class);

        app(WhatsAppSender::class)->sendFor($team, OutgoingWhatsAppMessage::text('08123456789', 'Halo.'));
    }

    public function test_free_text_is_allowed_inside_the_service_window(): void
    {
        Http::fake(['graph.example.test/*' => Http::response(['messages' => [['id' => 'wamid.1']]])]);
        $team = $this->tenant(lastInbound: '-23 hours');

        $this->assertSame('wamid.1', app(WhatsAppSender::class)->sendFor($team, OutgoingWhatsAppMessage::text('08123456789', 'Halo.')));
    }

    public function test_a_template_is_sent_outside_the_service_window(): void
    {
        Http::fake(['graph.example.test/*' => Http::response(['messages' => [['id' => 'wamid.T']]])]);
        $team = $this->tenant(lastInbound: '-40 hours');

        $messageId = app(WhatsAppSender::class)->sendFor(
            $team,
            OutgoingWhatsAppMessage::template('08123456789', 'follow_up_penawaran', ['Budi', 'Website'], 'id'),
        );

        $this->assertSame('wamid.T', $messageId);

        Http::assertSent(function ($request): bool {
            return $request['type'] === 'template'
                && $request['template']['name'] === 'follow_up_penawaran'
                && $request['template']['language']['code'] === 'id'
                && $request['template']['components'][0]['parameters'][0]['text'] === 'Budi'
                && $request['template']['components'][0]['parameters'][1]['text'] === 'Website';
        });
    }

    public function test_a_template_without_the_default_language_uses_config(): void
    {
        $message = OutgoingWhatsAppMessage::template('08123456789', 'hello_world');

        $this->assertSame('id', $message->templateLanguage);
        $this->assertSame([], $message->parameters);
        $this->assertTrue($message->isTemplate());
    }

    public function test_the_driver_binding_injects_the_quota_aware_sender(): void
    {
        RateLimiter::clear('whatsapp:send:1');

        $this->assertInstanceOf(WhatsAppSender::class, app(WhatsAppSender::class));
        $this->assertInstanceOf(WhatsAppDriver::class, app(WhatsAppDriver::class));
    }
}
