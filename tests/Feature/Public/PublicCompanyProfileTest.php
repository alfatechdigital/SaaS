<?php

namespace Tests\Feature\Public;

use App\Contracts\TeamEntitlementSource;
use App\Enums\LeadStatus;
use App\Enums\TeamRole;
use App\Models\CompanyProfile;
use App\Models\Lead;
use App\Models\PortfolioItem;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Teams\ContactMessageReceived;
use App\Support\Entitlements;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Domain\DomainTestCase;
use Tests\Support\FakeTeamEntitlementSource;

/**
 * Covers the public (unauthenticated) company profile page and the contact form
 * that emails the owner and, on plans with `lead_management`, also files a lead.
 * See docs/IMPLEMENTATION_PLAN.md §6 Fase 4.3 and F-7 (16 Nov).
 */
class PublicCompanyProfileTest extends DomainTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    /**
     * Run the contact form on the default Basic plan.
     */
    private function useBasicPlan(): void
    {
        $this->app->instance(TeamEntitlementSource::class, new FakeTeamEntitlementSource('basic'));
        $this->app->forgetInstance(Entitlements::class);
    }

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
     * The contact form is the only unauthenticated write endpoint, so it is the
     * one that must never guess the tenant: the lead is filed under the team in
     * the URL, and under no other.
     */
    public function test_the_contact_form_files_the_lead_under_the_team_in_the_url(): void
    {
        $otherTeam = Team::factory()->create();
        $otherOwner = User::factory()->create();
        $otherTeam->members()->attach($otherOwner, ['role' => TeamRole::Owner->value]);

        $this->post(route('public.contact.store', ['team' => $otherTeam->slug]), $this->payload())
            ->assertRedirect();

        $this->assertDatabaseCount('leads', 1);
        $this->assertDatabaseHas('leads', ['team_id' => $otherTeam->id]);
        $this->assertDatabaseMissing('leads', ['team_id' => $this->team->id]);
        Notification::assertSentTo($otherOwner, ContactMessageReceived::class);
    }

    public function test_unknown_team_slug_returns_not_found(): void
    {
        $this->get(route('public.company-profile', ['team' => 'tim-tidak-ada']))
            ->assertNotFound();
    }

    public function test_the_contact_form_emails_the_owner_without_a_lead_on_the_basic_plan(): void
    {
        $this->useBasicPlan();

        $this->post(route('public.contact.store', ['team' => $this->team->slug]), $this->payload())
            ->assertRedirect();

        Notification::assertSentTo(
            $this->owner,
            ContactMessageReceived::class,
            fn (ContactMessageReceived $notification): bool => $notification->name === 'Hendra Setiawan'
                && $notification->contact === '0812-3456-7890'
                && str_contains($notification->message, 'gudang'),
        );

        // Basic has no lead module, so there is nowhere to store the message.
        $this->assertDatabaseCount('leads', 0);
    }

    public function test_the_contact_form_files_a_lead_when_the_plan_includes_lead_management(): void
    {
        $this->post(route('public.contact.store', ['team' => $this->team->slug]), $this->payload())
            ->assertRedirect();

        $lead = Lead::query()->where('team_id', $this->team->id)->firstOrFail();

        $this->assertSame('Hendra Setiawan', $lead->contact_name);
        $this->assertSame('Hendra Setiawan', $lead->company_name);
        $this->assertSame('0812-3456-7890', $lead->phone);
        $this->assertNull($lead->email);
        $this->assertSame(LeadStatus::New, $lead->status);
        $this->assertSame('Website Publik (Form Kontak)', $lead->source);
        $this->assertStringContainsString('gudang', (string) $lead->notes);
        $this->assertSame(0, $lead->estimated_value);

        Notification::assertSentTo($this->owner, ContactMessageReceived::class);
    }

    public function test_an_email_contact_is_stored_in_the_email_column(): void
    {
        $this->post(
            route('public.contact.store', ['team' => $this->team->slug]),
            $this->payload(['contact' => 'hendra@perusahaan.co.id']),
        )->assertRedirect();

        $lead = Lead::query()->where('team_id', $this->team->id)->firstOrFail();

        $this->assertNull($lead->phone);
        $this->assertSame('hendra@perusahaan.co.id', $lead->email);
    }

    public function test_the_contact_form_requires_name_contact_and_message(): void
    {
        $this->post(route('public.contact.store', ['team' => $this->team->slug]), [])
            ->assertSessionHasErrors(['name', 'contact', 'message']);

        $this->assertDatabaseCount('leads', 0);
        Notification::assertNothingSent();
    }

    public function test_whitespace_only_fields_are_rejected(): void
    {
        $this->post(route('public.contact.store', ['team' => $this->team->slug]), [
            'name' => '   ',
            'contact' => '  ',
            'message' => ' ',
        ])->assertSessionHasErrors(['name', 'contact', 'message']);

        $this->assertDatabaseCount('leads', 0);
        Notification::assertNothingSent();
    }

    public function test_the_contact_form_for_an_unknown_team_returns_not_found(): void
    {
        $this->post(route('public.contact.store', ['team' => 'tim-tidak-ada']), $this->payload())
            ->assertNotFound();

        $this->assertDatabaseCount('leads', 0);
        Notification::assertNothingSent();
    }

    public function test_a_deactivated_public_page_is_not_available(): void
    {
        // Moderation (PDR-12): the operator can take a tenant's page offline
        // without deleting any of its data.
        $this->team->update(['public_page_enabled' => false]);

        $this->get($this->publicRoute())->assertNotFound();
    }

    public function test_the_contact_form_is_rejected_when_the_public_page_is_deactivated(): void
    {
        $this->team->update(['public_page_enabled' => false]);

        $this->post(
            route('public.contact.store', ['team' => $this->team->slug]),
            $this->payload(),
        )->assertNotFound();

        $this->assertDatabaseCount('leads', 0);
        Notification::assertNothingSent();
    }

    public function test_the_public_page_works_again_after_being_reactivated(): void
    {
        $this->team->update(['public_page_enabled' => false]);
        $this->get($this->publicRoute())->assertNotFound();

        $this->team->update(['public_page_enabled' => true]);
        $this->get($this->publicRoute())->assertOk();
    }

    public function test_meta_title_and_description_come_from_the_tenant_profile(): void
    {
        CompanyProfile::factory()->create([
            'team_id' => $this->team->id,
            'company_name' => 'PT Contoh Digital',
            'description' => 'Kami membangun aplikasi ERP dan portal pelanggan.',
            'about' => 'Teks ini tidak dipakai karena `description` sudah terisi.',
        ]);

        $this->get($this->publicRoute())
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('meta.title', 'PT Contoh Digital')
                ->where('meta.description', 'Kami membangun aplikasi ERP dan portal pelanggan.'));
    }

    public function test_meta_falls_back_to_the_team_name_when_there_is_no_profile(): void
    {
        $this->get($this->publicRoute())
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('meta.title', $this->team->name)
                ->where('meta.description', 'Profil '.$this->team->name.': layanan, portofolio, dan formulir kontak.'));
    }

    public function test_meta_description_is_plain_text_and_bounded(): void
    {
        CompanyProfile::factory()->create([
            'team_id' => $this->team->id,
            'company_name' => 'PT Contoh',
            'description' => null,
            'about' => '<p>'.str_repeat('Layanan digital terpercaya. ', 20).'</p>',
        ]);

        $description = null;

        $this->get($this->publicRoute())
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$description): void {
                $description = $page->toArray()['props']['meta']['description'];
            });

        $this->assertIsString($description);
        $this->assertStringNotContainsString('<p>', $description);
        $this->assertLessThanOrEqual(163, mb_strlen($description));
    }

    public function test_the_public_page_does_not_expose_plan_entitlements(): void
    {
        $props = null;

        $this->get($this->publicRoute())
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$props): void {
                $props = $page->toArray()['props'];
            });

        $this->assertIsArray($props);
        $this->assertSame([], $props['features']);
        $this->assertSame([], $props['limits']);
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
            'name' => 'Hendra Setiawan',
            'contact' => '0812-3456-7890',
            'message' => 'Butuh integrasi dengan sistem gudang lama.',
            ...$overrides,
        ];
    }
}
