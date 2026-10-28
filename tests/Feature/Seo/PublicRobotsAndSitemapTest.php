<?php

namespace Tests\Feature\Seo;

use App\Models\CompanyProfile;
use App\Models\PortfolioItem;
use App\Models\Team;
use SimpleXMLElement;
use Tests\Feature\Domain\DomainTestCase;

/**
 * Fase 3 / tugas 3.5.7–3.5.9 — per-tenant `robots.txt` and `sitemap.xml`.
 *
 * The two artefacts must agree with the page: whatever hides the page also hides
 * it from crawlers. That shared answer lives in `Team::isPublicPageVisible()`,
 * and these tests pin its three consequences.
 *
 * @see \docs\implementation\phase-03-domain-dan-seo.md tugas 3.5.7 s/d 3.5.9
 */
class PublicRobotsAndSitemapTest extends DomainTestCase
{
    public function test_robots_allows_crawling_and_points_at_the_sitemap(): void
    {
        $response = $this->get($this->robotsRoute($this->team))->assertOk();

        $this->assertStringContainsString('text/plain', (string) $response->headers->get('Content-Type'));

        $body = $response->getContent();

        $this->assertIsString($body);
        $this->assertStringContainsString("User-agent: *\n", $body);
        $this->assertStringContainsString("Allow: /\n", $body);
        $this->assertStringContainsString('Sitemap: '.route('public.sitemap', ['team' => $this->team->slug]), $body);
        $this->assertStringNotContainsString('Disallow: /', $body);
    }

    public function test_a_moderated_tenant_disallows_crawling(): void
    {
        $this->team->update(['public_page_enabled' => false]);

        $body = $this->get($this->robotsRoute($this->team))->assertOk()->getContent();

        $this->assertIsString($body);
        $this->assertStringContainsString('Disallow: /', $body);
        $this->assertStringNotContainsString('Sitemap:', $body);
    }

    public function test_a_suspended_tenant_disallows_crawling(): void
    {
        $this->team->update(['suspended_at' => now()]);

        $body = $this->get($this->robotsRoute($this->team))->assertOk()->getContent();

        $this->assertIsString($body);
        $this->assertStringContainsString('Disallow: /', $body);
    }

    public function test_the_sitemap_is_valid_xml_with_the_tenant_page(): void
    {
        $response = $this->get($this->sitemapRoute($this->team))->assertOk();

        $this->assertStringContainsString('xml', (string) $response->headers->get('Content-Type'));

        $urls = $this->parse($response->getContent());

        $this->assertCount(1, $urls);
        $this->assertSame(route('public.company-profile', ['team' => $this->team->slug]), $urls[0]['loc']);
    }

    public function test_the_sitemap_lastmod_follows_the_newest_change(): void
    {
        $this->travelTo(now()->startOfDay()->subDays(10));

        CompanyProfile::factory()->create([
            'team_id' => $this->team->id,
            'company_name' => 'PT Contoh Digital',
        ]);

        $this->travelTo(now()->startOfDay()->addDays(5));

        PortfolioItem::factory()->create([
            'team_id' => $this->team->id,
            'published' => true,
        ]);

        $this->travelBack();

        $urls = $this->parse($this->get($this->sitemapRoute($this->team))->assertOk()->getContent());

        $this->assertSame(now()->startOfDay()->subDays(5)->toDateString(), $urls[0]['lastmod']);
    }

    public function test_the_sitemap_ignores_unpublished_portfolio_items(): void
    {
        $this->travelTo(now()->startOfDay()->subDays(10));

        CompanyProfile::factory()->create([
            'team_id' => $this->team->id,
            'company_name' => 'PT Contoh Digital',
        ]);

        $this->travelTo(now()->startOfDay()->addDays(3));

        PortfolioItem::factory()->create([
            'team_id' => $this->team->id,
            'published' => false,
        ]);

        $this->travelBack();

        $urls = $this->parse($this->get($this->sitemapRoute($this->team))->assertOk()->getContent());

        $this->assertSame(now()->startOfDay()->subDays(10)->toDateString(), $urls[0]['lastmod']);
    }

    public function test_a_moderated_tenant_has_no_sitemap(): void
    {
        $this->team->update(['public_page_enabled' => false]);

        $this->get($this->sitemapRoute($this->team))->assertNotFound();
    }

    public function test_a_suspended_tenant_has_no_sitemap(): void
    {
        $this->team->update(['suspended_at' => now()]);

        $this->get($this->sitemapRoute($this->team))->assertNotFound();
    }

    /**
     * Tugas 3.5.9 — a hidden page tells crawlers not to index it, even if the URL
     * is already known to them.
     */
    public function test_a_hidden_public_page_asks_crawlers_not_to_index_it(): void
    {
        $this->team->update(['public_page_enabled' => false]);

        $this->get(route('public.company-profile', ['team' => $this->team->slug]))
            ->assertNotFound()
            ->assertHeader('X-Robots-Tag', 'noindex');
    }

    public function test_an_unknown_tenant_slug_is_not_found(): void
    {
        $this->get(route('public.robots', ['team' => 'tidak-ada']))->assertNotFound();
        $this->get(route('public.sitemap', ['team' => 'tidak-ada']))->assertNotFound();
    }

    /**
     * Parse the sitemap with SimpleXML instead of matching strings: a document
     * that only *looks* like XML (unescaped `&`, wrong namespace) fails here.
     *
     * @return list<array{loc: string, lastmod: string|null}>
     */
    private function parse(?string $body): array
    {
        $this->assertIsString($body);

        $xml = simplexml_load_string($body);

        $this->assertInstanceOf(SimpleXMLElement::class, $xml);
        $this->assertSame('urlset', $xml->getName());
        $this->assertSame('http://www.sitemaps.org/schemas/sitemap/0.9', $xml->getNamespaces()['']);

        $urls = [];

        foreach ($xml->url as $url) {
            $urls[] = [
                'loc' => (string) $url->loc,
                'lastmod' => isset($url->lastmod) ? (string) $url->lastmod : null,
            ];
        }

        return $urls;
    }

    private function robotsRoute(Team $team): string
    {
        return route('public.robots', ['team' => $team->slug]);
    }

    private function sitemapRoute(Team $team): string
    {
        return route('public.sitemap', ['team' => $team->slug]);
    }
}
