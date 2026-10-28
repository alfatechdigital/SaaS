<?php

namespace Tests\Feature\Seo;

use App\Models\CompanyProfile;
use App\Models\PortfolioItem;
use App\Models\Team;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Domain\DomainTestCase;

/**
 * Fase 3 / tugas 3.5.2–3.5.6 — SEO foundation of the tenant's public page.
 *
 * Every assertion reads the page's own payload. That is deliberate: the values
 * must come from *this* tenant's profile, and a shared marketing string or a
 * hardcoded domain would show up here as a wrong value rather than as a
 * plausible-looking page.
 *
 * @see \docs\implementation\phase-03-domain-dan-seo.md tugas 3.5.2 s/d 3.5.6
 */
class PublicPageSeoTest extends DomainTestCase
{
    public function test_the_canonical_url_is_absolute_and_tenant_specific(): void
    {
        $meta = $this->metaFor($this->team);

        $this->assertSame(route('public.company-profile', ['team' => $this->team->slug]), $meta['canonical']);
        $this->assertStringStartsWith('http', $meta['canonical']);
        $this->assertStringContainsString($this->team->slug, $meta['canonical']);
    }

    public function test_open_graph_mirrors_the_page_metadata(): void
    {
        CompanyProfile::factory()->create([
            'team_id' => $this->team->id,
            'company_name' => 'PT Contoh Digital',
            'description' => 'Kami membangun aplikasi ERP.',
        ]);

        $meta = $this->metaFor($this->team);

        $this->assertSame('website', $meta['og']['type']);
        $this->assertSame('PT Contoh Digital', $meta['og']['title']);
        $this->assertSame('Kami membangun aplikasi ERP.', $meta['og']['description']);
        $this->assertSame($meta['canonical'], $meta['og']['url']);
        $this->assertSame($this->team->name, $meta['og']['siteName']);
    }

    public function test_the_social_image_comes_from_a_published_portfolio_item(): void
    {
        PortfolioItem::factory()->create([
            'team_id' => $this->team->id,
            'published' => true,
            'image_url' => 'https://cdn.example.com/karya.jpg',
        ]);

        $meta = $this->metaFor($this->team);

        $this->assertSame('https://cdn.example.com/karya.jpg', $meta['og']['image']);
        $this->assertSame('https://cdn.example.com/karya.jpg', $meta['twitter']['image']);
        $this->assertSame('summary_large_image', $meta['twitter']['card']);
    }

    public function test_a_draft_portfolio_image_is_not_used_as_the_social_image(): void
    {
        PortfolioItem::factory()->create([
            'team_id' => $this->team->id,
            'published' => false,
            'image_url' => 'https://cdn.example.com/draf.jpg',
        ]);

        $meta = $this->metaFor($this->team);

        $this->assertNull($meta['og']['image']);
        $this->assertSame('summary', $meta['twitter']['card']);
    }

    public function test_a_relative_portfolio_image_is_ignored(): void
    {
        PortfolioItem::factory()->create([
            'team_id' => $this->team->id,
            'published' => true,
            'image_url' => '/storage/karya.jpg',
        ]);

        $this->assertNull($this->metaFor($this->team)['og']['image']);
    }

    public function test_the_structured_data_describes_the_tenant_as_a_local_business(): void
    {
        CompanyProfile::factory()->create([
            'team_id' => $this->team->id,
            'company_name' => 'PT Contoh Digital',
            'description' => 'Kami membangun aplikasi ERP.',
            'phone' => '021-555-0100',
            'email' => 'halo@contoh.co.id',
            'address' => 'Jl. Merdeka 10, Jakarta',
        ]);

        $meta = $this->metaFor($this->team);
        $jsonLd = $meta['jsonLd'];

        $this->assertSame('https://schema.org', $jsonLd['@context']);
        $this->assertSame('LocalBusiness', $jsonLd['@type']);
        $this->assertSame('PT Contoh Digital', $jsonLd['name']);
        $this->assertSame($meta['canonical'], $jsonLd['url']);
        $this->assertSame('021-555-0100', $jsonLd['telephone']);
        $this->assertSame('halo@contoh.co.id', $jsonLd['email']);
        $this->assertSame([
            '@type' => 'PostalAddress',
            'streetAddress' => 'Jl. Merdeka 10, Jakarta',
        ], $jsonLd['address']);
    }

    public function test_empty_profile_fields_are_left_out_of_the_structured_data(): void
    {
        CompanyProfile::factory()->create([
            'team_id' => $this->team->id,
            'company_name' => 'PT Contoh Digital',
            'phone' => null,
            'email' => null,
            'address' => null,
        ]);

        $jsonLd = $this->metaFor($this->team)['jsonLd'];

        $this->assertArrayNotHasKey('telephone', $jsonLd);
        $this->assertArrayNotHasKey('email', $jsonLd);
        $this->assertArrayNotHasKey('address', $jsonLd);
        $this->assertArrayNotHasKey('image', $jsonLd);
    }

    public function test_the_head_payload_contains_one_json_ld_script(): void
    {
        CompanyProfile::factory()->create([
            'team_id' => $this->team->id,
            'company_name' => 'PT Contoh Digital',
        ]);

        $head = $this->headFor($this->team);

        $this->assertCount(1, $head);
        $this->assertStringStartsWith('<script type="application/ld+json">', $head[0]);
        $this->assertStringEndsWith('</script>', $head[0]);
        $this->assertStringContainsString('"@type":"LocalBusiness"', $head[0]);
    }

    /**
     * A company name is user input. Unescaped, `</script>` inside the JSON would
     * end the tag and let the rest of the name run as markup.
     */
    public function test_a_company_name_cannot_break_out_of_the_json_ld_script(): void
    {
        CompanyProfile::factory()->create([
            'team_id' => $this->team->id,
            'company_name' => 'PT </script><script>alert(1)</script>',
        ]);

        $head = $this->headFor($this->team)[0];

        $this->assertStringNotContainsString('</script><script>', $head);
        $this->assertStringContainsString('\u003C/script\u003E', $head);
    }

    /**
     * The kickoff for tugas 3.5.10: two tenants must never render each other's
     * identity. Both are named almost identically on purpose, so a leaked value
     * cannot pass by looking plausible.
     */
    public function test_two_tenants_render_their_own_metadata(): void
    {
        $otherTeam = Team::factory()->create();

        CompanyProfile::factory()->create([
            'team_id' => $this->team->id,
            'company_name' => 'PT Kembar Satu',
            'email' => 'satu@contoh.co.id',
        ]);

        CompanyProfile::factory()->create([
            'team_id' => $otherTeam->id,
            'company_name' => 'PT Kembar Dua',
            'email' => 'dua@contoh.co.id',
        ]);

        $mine = $this->metaFor($this->team);
        $theirs = $this->metaFor($otherTeam);

        $this->assertSame('PT Kembar Satu', $mine['title']);
        $this->assertSame('PT Kembar Dua', $theirs['title']);
        $this->assertNotSame($mine['canonical'], $theirs['canonical']);
        $this->assertSame('satu@contoh.co.id', $mine['jsonLd']['email']);
        $this->assertSame('dua@contoh.co.id', $theirs['jsonLd']['email']);
    }

    /**
     * @return array<string, mixed>
     */
    private function metaFor(Team $team): array
    {
        $props = $this->propsFor($team);

        $this->assertIsArray($props['meta']);

        return $props['meta'];
    }

    /**
     * @return list<string>
     */
    private function headFor(Team $team): array
    {
        $props = $this->propsFor($team);

        $this->assertIsArray($props['seoHead']);

        return array_values($props['seoHead']);
    }

    /**
     * @return array<string, mixed>
     */
    private function propsFor(Team $team): array
    {
        $props = [];

        $this->get(route('public.company-profile', ['team' => $team->slug]))
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$props): void {
                $props = $page->toArray()['props'];
            });

        return $props;
    }
}
