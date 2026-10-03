<?php

namespace Tests\Feature\Public;

use App\Enums\LeadStatus;
use App\Models\CompanyProfile;
use App\Models\Lead;
use App\Models\PortfolioItem;
use App\Models\Team;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Domain\DomainTestCase;

/**
 * Covers the public (unauthenticated) company profile page and the consultation
 * form that files a lead. See docs/IMPLEMENTATION_PLAN.md §6 Fase 4.3.
 */
class PublicCompanyProfileTest extends DomainTestCase
{
    public function test_guest_can_view_the_public_company_profile(): void
    {
        $this->get($this->publicRoute())
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/company-profile')
                ->where('team.slug', $this->team->slug)
                ->has('portfolio'));
    }

    public function test_only_published_portfolio_items_are_exposed(): void
    {
        PortfolioItem::factory()->create([
            'team_id' => $this->team->id,
            'title' => 'Proyek Terbit',
            'published' => true,
        ]);

        PortfolioItem::factory()->create([
            'team_id' => $this->team->id,
            'title' => 'Proyek Draf',
            'published' => false,
        ]);

        $this->get($this->publicRoute())
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('portfolio', 1)
                ->where('portfolio.0.title', 'Proyek Terbit'));
    }

    public function test_portfolio_items_of_other_teams_are_not_exposed(): void
    {
        $otherTeam = Team::factory()->create();

        PortfolioItem::factory()->create([
            'team_id' => $otherTeam->id,
            'published' => true,
        ]);

        $this->get($this->publicRoute())
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('portfolio', 0));
    }

    /**
     * P-1 / tugas 1.2.5.
     *
     * Both tenants are given the exact same names, so a leak cannot be spotted
     * by reading the payload — only the ids can tell the tenants apart.
     *
     * Both pages are checked on purpose. The profile is fetched with a bare
     * `first()`, so an unscoped query would return the *same* row for both
     * tenants; asserting only one page could then pass by accident, depending on
     * which row the database happens to return first. Requiring each page to show
     * its own row makes the test deterministic. The portfolio count is
     * order-independent for the same reason.
     */
    public function test_a_public_page_does_not_leak_another_tenants_data(): void
    {
        $otherTeam = Team::factory()->create();

        $mine = CompanyProfile::factory()->create([
            'team_id' => $this->team->id,
            'company_name' => 'PT Data Kembar',
        ]);

        $theirs = CompanyProfile::factory()->create([
            'team_id' => $otherTeam->id,
            'company_name' => 'PT Data Kembar',
        ]);

        $myItem = PortfolioItem::factory()->create([
            'team_id' => $this->team->id,
            'title' => 'Portfolio Kembar',
            'published' => true,
        ]);

        $theirItem = PortfolioItem::factory()->create([
            'team_id' => $otherTeam->id,
            'title' => 'Portfolio Kembar',
            'published' => true,
        ]);

        $this->get(route('public.company-profile', ['team' => $this->team->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/company-profile')
                ->where('profile.id', $mine->id)
                ->has('portfolio', 1)
                ->where('portfolio.0.id', $myItem->id));

        $this->get(route('public.company-profile', ['team' => $otherTeam->slug]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/company-profile')
                ->where('profile.id', $theirs->id)
                ->has('portfolio', 1)
                ->where('portfolio.0.id', $theirItem->id));
    }

    /**
     * P-1 / tugas 1.2.6.
     *
     * The consultation form is the only unauthenticated write endpoint, so it is
     * the one that must never guess the tenant: the lead is filed under the team
     * in the URL, and under no other.
     */
    public function test_the_consultation_form_files_the_lead_under_the_team_in_the_url(): void
    {
        $otherTeam = Team::factory()->create();

        $this->post(route('public.consultation.store', ['team' => $otherTeam->slug]), $this->payload())
            ->assertRedirect();

        $this->assertDatabaseCount('leads', 1);
        $this->assertDatabaseHas('leads', ['team_id' => $otherTeam->id]);
        $this->assertDatabaseMissing('leads', ['team_id' => $this->team->id]);
    }

    public function test_unknown_team_slug_returns_not_found(): void
    {
        $this->get(route('public.company-profile', ['team' => 'tim-tidak-ada']))
            ->assertNotFound();
    }

    public function test_consultation_form_creates_a_new_lead_for_the_team(): void
    {
        $this->post(route('public.consultation.store', ['team' => $this->team->slug]), $this->payload())
            ->assertRedirect();

        $lead = Lead::query()->where('team_id', $this->team->id)->firstOrFail();

        $this->assertSame('Hendra Setiawan', $lead->contact_name);
        $this->assertSame('PT Logistik Nusantara', $lead->company_name);
        $this->assertSame(LeadStatus::New, $lead->status);
        $this->assertSame(35_000_000, $lead->estimated_value);
        $this->assertSame('Website Publik (Form Konsultasi)', $lead->source);
    }

    public function test_company_name_falls_back_to_the_contact_name(): void
    {
        $this->post(
            route('public.consultation.store', ['team' => $this->team->slug]),
            $this->payload(['company_name' => '']),
        )->assertRedirect();

        $lead = Lead::query()->where('team_id', $this->team->id)->firstOrFail();

        $this->assertSame('Hendra Setiawan', $lead->company_name);
    }

    public function test_consultation_form_requires_contact_name_and_phone(): void
    {
        $this->post(
            route('public.consultation.store', ['team' => $this->team->slug]),
            ['company_name' => 'PT Tanpa Kontak'],
        )->assertSessionHasErrors(['contact_name', 'phone']);

        $this->assertDatabaseCount('leads', 0);
    }

    public function test_consultation_form_rejects_an_invalid_email(): void
    {
        $this->post(
            route('public.consultation.store', ['team' => $this->team->slug]),
            $this->payload(['email' => 'bukan-email']),
        )->assertSessionHasErrors('email');
    }

    public function test_consultation_form_for_unknown_team_returns_not_found(): void
    {
        $this->post(route('public.consultation.store', ['team' => 'tim-tidak-ada']), $this->payload())
            ->assertNotFound();

        $this->assertDatabaseCount('leads', 0);
    }

    public function test_a_deactivated_public_page_is_not_available(): void
    {
        // Moderation (PDR-12): the operator can take a tenant's page offline
        // without deleting any of its data.
        $this->team->update(['public_page_enabled' => false]);

        $this->get($this->publicRoute())->assertNotFound();
    }

    public function test_the_consultation_form_is_rejected_when_the_public_page_is_deactivated(): void
    {
        $this->team->update(['public_page_enabled' => false]);

        $this->post(
            route('public.consultation.store', ['team' => $this->team->slug]),
            $this->payload(),
        )->assertNotFound();

        $this->assertDatabaseCount('leads', 0);
    }

    public function test_the_public_page_works_again_after_being_reactivated(): void
    {
        $this->team->update(['public_page_enabled' => false]);
        $this->get($this->publicRoute())->assertNotFound();

        $this->team->update(['public_page_enabled' => true]);
        $this->get($this->publicRoute())->assertOk();
    }

    private function publicRoute(): string
    {
        return route('public.company-profile', ['team' => $this->team->slug]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'contact_name' => 'Hendra Setiawan',
            'company_name' => 'PT Logistik Nusantara',
            'phone' => '0812-3456-7890',
            'email' => 'hendra@perusahaan.co.id',
            'service_type' => 'Custom ERP & Web Platform',
            'budget_estimate' => 35_000_000,
            'notes' => 'Butuh integrasi dengan sistem gudang lama.',
            ...$overrides,
        ];
    }
}
