<?php

namespace Tests\Feature\Seo;

use App\Jobs\PingSitemapJob;
use App\Models\CompanyProfile;
use App\Models\PortfolioItem;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Domain\DomainTestCase;

/**
 * B-8 — SEO automation: a change on the public page notifies search engines.
 *
 * The queue runs synchronously under test, so creating a model here really does
 * run `PingSitemapJob`. That is the point: the assertions read the outgoing HTTP
 * requests, not the job's internals. `Http::preventStrayRequests()` makes any
 * request the tests did not plan for fail loudly instead of reaching the network.
 *
 * @see \docs\implementation\phase-03-domain-dan-seo.md
 */
class SitemapPingTest extends DomainTestCase
{
    private const ENDPOINT = 'https://ping.example.com/notify?url={sitemap}';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'seo.ping.enabled' => true,
            'seo.ping.urls' => [self::ENDPOINT],
            'seo.ping.timeout' => 1,
        ]);

        Http::preventStrayRequests();
        Http::fake(['ping.example.com/*' => Http::response('', 200)]);
    }

    public function test_a_profile_change_pings_the_sitemap_of_its_own_tenant(): void
    {
        CompanyProfile::factory()->create(['team_id' => $this->team->id]);

        $this->assertPingedFor($this->team);
        Http::assertSentCount(1);
    }

    public function test_publishing_a_portfolio_item_pings_the_sitemap(): void
    {
        $item = PortfolioItem::factory()->create([
            'team_id' => $this->team->id,
            'published' => false,
        ]);

        // A draft was not advertised, so nothing has been pinged yet.
        Http::assertNothingSent();

        $item->update(['published' => true]);

        $this->assertPingedFor($this->team);
    }

    public function test_an_unpublished_portfolio_item_is_not_advertised(): void
    {
        PortfolioItem::factory()->create([
            'team_id' => $this->team->id,
            'published' => false,
        ]);

        Http::assertNothingSent();
    }

    public function test_the_same_sitemap_is_not_pinged_twice(): void
    {
        CompanyProfile::factory()->create(['team_id' => $this->team->id]);

        PingSitemapJob::dispatchSync($this->team->id);

        Http::assertSentCount(1);
    }

    public function test_a_moderated_tenant_is_never_pinged(): void
    {
        $this->team->update(['public_page_enabled' => false]);

        Http::assertNothingSent();
    }

    public function test_a_suspended_tenant_is_never_pinged(): void
    {
        $this->team->update(['suspended_at' => now()]);

        Http::assertNothingSent();
    }

    public function test_a_tenant_without_a_configured_endpoint_pings_nothing(): void
    {
        config(['seo.ping.urls' => []]);

        CompanyProfile::factory()->create(['team_id' => $this->team->id]);

        Http::assertNothingSent();
    }

    public function test_pinging_can_be_switched_off(): void
    {
        config(['seo.ping.enabled' => false]);

        CompanyProfile::factory()->create(['team_id' => $this->team->id]);

        Http::assertNothingSent();
    }

    public function test_a_tenant_only_ever_advertises_its_own_sitemap(): void
    {
        $other = Team::factory()->create();

        CompanyProfile::factory()->create(['team_id' => $this->team->id]);
        CompanyProfile::factory()->create(['team_id' => $other->id]);

        $this->assertPingedFor($this->team);
        $this->assertPingedFor($other);

        $expected = array_map(
            static fn (Team $team): string => 'https://ping.example.com/notify?url='
                .rawurlencode(route('public.sitemap', ['team' => $team->slug])),
            [$this->team, $other],
        );

        foreach (Http::recorded() as [$request]) {
            $this->assertContains($request->url(), $expected);
        }
    }

    public function test_a_rejected_ping_does_not_fail_the_save(): void
    {
        Http::fake(['ping.example.com/*' => Http::response('', 404)]);

        CompanyProfile::factory()->create(['team_id' => $this->team->id]);

        Http::assertSentCount(1);
    }

    public function test_an_unreachable_endpoint_does_not_fail_the_save(): void
    {
        Http::fake(['ping.example.com/*' => fn () => throw new ConnectionException('down')]);

        $profile = CompanyProfile::factory()->create(['team_id' => $this->team->id]);

        $this->assertDatabaseHas('company_profiles', ['id' => $profile->id]);
    }

    public function test_models_outside_the_public_page_do_not_queue_pings(): void
    {
        Queue::fake();

        $project = Project::factory()->create(['team_id' => $this->team->id]);

        Task::factory()->create([
            'team_id' => $this->team->id,
            'project_id' => $project->id,
        ]);

        Queue::assertNothingPushed();
    }

    public function test_every_public_change_queues_a_ping_for_its_tenant(): void
    {
        Queue::fake();

        CompanyProfile::factory()->create(['team_id' => $this->team->id]);
        PortfolioItem::factory()->create(['team_id' => $this->team->id, 'published' => true]);

        Queue::assertPushed(PingSitemapJob::class, 2);
        Queue::assertPushed(
            PingSitemapJob::class,
            fn (PingSitemapJob $job): bool => $job->teamId === $this->team->id,
        );
    }

    /**
     * A ping must carry the tenant's own sitemap URL, URL-encoded as the query
     * value it is.
     */
    private function assertPingedFor(Team $team): void
    {
        $sitemap = route('public.sitemap', ['team' => $team->slug]);

        Http::assertSent(function (Request $request) use ($sitemap): bool {
            return $request->url() === 'https://ping.example.com/notify?url='.rawurlencode($sitemap);
        });
    }
}
