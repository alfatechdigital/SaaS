<?php

namespace Tests\Feature\Seo;

use App\Models\CompanyProfile;
use App\Models\PortfolioItem;
use App\Models\Team;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Domain\DomainTestCase;

/**
 * Fase 3 / tugas 3.5.10 — all SEO artefacts, checked for two tenants at once.
 *
 * The tenants are given *different* data on purpose, and every artefact of each
 * tenant is asserted to mention its own slug and not the other's. A value built
 * from the wrong tenant shows up as a mismatch rather than as a plausible page.
 *
 * @see \docs\implementation\phase-03-domain-dan-seo.md tugas 3.5.10
 */
class PublicSeoAcrossTenantsTest extends DomainTestCase
{
    private Team $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->other = Team::factory()->create();

        CompanyProfile::factory()->create([
            'team_id' => $this->team->id,
            'company_name' => 'CV Sinar Jaya',
            'description' => 'Layanan pemasangan jaringan untuk pabrik.',
            'email' => 'halo@sinarjaya.co.id',
            'phone' => '021-111-2222',
            'address' => 'Jl. Industri 1, Bekasi',
        ]);

        CompanyProfile::factory()->create([
            'team_id' => $this->other->id,
            'company_name' => 'PT Bumi Digital',
            'description' => 'Studio produk digital untuk ritel.',
            'email' => 'kontak@bumidigital.id',
            'phone' => '031-333-4444',
            'address' => 'Jl. Pemuda 88, Surabaya',
        ]);

        PortfolioItem::factory()->create([
            'team_id' => $this->team->id,
            'published' => true,
            'image_url' => 'https://cdn.example.com/sinar.jpg',
        ]);

        PortfolioItem::factory()->create([
            'team_id' => $this->other->id,
            'published' => true,
            'image_url' => 'https://cdn.example.com/bumi.jpg',
        ]);
    }

    public function test_each_tenant_renders_its_own_meta_and_structured_data(): void
    {
        $mine = $this->metaFor($this->team);
        $theirs = $this->metaFor($this->other);

        $this->assertSame('CV Sinar Jaya', $mine['title']);
        $this->assertSame('PT Bumi Digital', $theirs['title']);

        $this->assertStringContainsString($this->team->slug, $mine['canonical']);
        $this->assertStringNotContainsString($this->other->slug, $mine['canonical']);

        $this->assertStringContainsString($this->other->slug, $theirs['canonical']);
        $this->assertStringNotContainsString($this->team->slug, $theirs['canonical']);

        $this->assertSame($mine['canonical'], $mine['og']['url']);
        $this->assertSame($theirs['canonical'], $theirs['og']['url']);

        $this->assertSame('https://cdn.example.com/sinar.jpg', $mine['og']['image']);
        $this->assertSame('https://cdn.example.com/bumi.jpg', $theirs['og']['image']);

        $this->assertSame('CV Sinar Jaya', $mine['jsonLd']['name']);
        $this->assertSame('halo@sinarjaya.co.id', $mine['jsonLd']['email']);
        $this->assertSame('Jl. Industri 1, Bekasi', $mine['jsonLd']['address']['streetAddress']);

        $this->assertSame('PT Bumi Digital', $theirs['jsonLd']['name']);
        $this->assertSame('kontak@bumidigital.id', $theirs['jsonLd']['email']);
        $this->assertSame('Jl. Pemuda 88, Surabaya', $theirs['jsonLd']['address']['streetAddress']);
    }

    public function test_each_tenant_advertises_only_its_own_sitemap(): void
    {
        $mine = $this->robotsBody($this->team);
        $theirs = $this->robotsBody($this->other);

        $this->assertStringContainsString(route('public.sitemap', ['team' => $this->team->slug]), $mine);
        $this->assertStringNotContainsString($this->other->slug, $mine);

        $this->assertStringContainsString(route('public.sitemap', ['team' => $this->other->slug]), $theirs);
        $this->assertStringNotContainsString($this->team->slug, $theirs);
    }

    public function test_each_sitemap_lists_only_its_own_page(): void
    {
        $mine = $this->sitemapBody($this->team);
        $theirs = $this->sitemapBody($this->other);

        $this->assertStringContainsString(route('public.company-profile', ['team' => $this->team->slug]), $mine);
        $this->assertStringNotContainsString($this->other->slug, $mine);

        $this->assertStringContainsString(route('public.company-profile', ['team' => $this->other->slug]), $theirs);
        $this->assertStringNotContainsString($this->team->slug, $theirs);
    }

    public function test_hiding_one_tenant_leaves_the_other_indexable(): void
    {
        $this->other->update(['public_page_enabled' => false]);

        $this->assertStringContainsString('Allow: /', $this->robotsBody($this->team));
        $this->assertStringContainsString('Disallow: /', $this->robotsBody($this->other));

        $this->get(route('public.sitemap', ['team' => $this->team->slug]))->assertOk();
        $this->get(route('public.sitemap', ['team' => $this->other->slug]))->assertNotFound();

        $this->get(route('public.company-profile', ['team' => $this->team->slug]))->assertOk();
        $this->get(route('public.company-profile', ['team' => $this->other->slug]))->assertNotFound();
    }

    /**
     * @return array<string, mixed>
     */
    private function metaFor(Team $team): array
    {
        $props = [];

        $this->get(route('public.company-profile', ['team' => $team->slug]))
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$props): void {
                $props = $page->toArray()['props'];
            });

        $this->assertIsArray($props['meta']);

        return $props['meta'];
    }

    private function robotsBody(Team $team): string
    {
        $body = $this->get(route('public.robots', ['team' => $team->slug]))->assertOk()->getContent();

        $this->assertIsString($body);

        return $body;
    }

    private function sitemapBody(Team $team): string
    {
        $body = $this->get(route('public.sitemap', ['team' => $team->slug]))->assertOk()->getContent();

        $this->assertIsString($body);

        return $body;
    }
}
