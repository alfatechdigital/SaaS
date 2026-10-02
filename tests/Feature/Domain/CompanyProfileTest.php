<?php

namespace Tests\Feature\Domain;

use App\Models\CompanyProfile;
use App\Models\User;

class CompanyProfileTest extends DomainTestCase
{
    public function test_owner_can_create_the_profile_on_first_update(): void
    {
        $this->actingAs($this->owner)
            ->put($this->teamRoute('company-profile.update'), $this->payload())
            ->assertRedirect();

        $this->assertDatabaseHas('company_profiles', [
            'team_id' => $this->team->id,
            'company_name' => 'PT Contoh Digital',
            'email' => 'contact@example.com',
        ]);
    }

    public function test_owner_can_update_an_existing_profile(): void
    {
        CompanyProfile::factory()->create(['team_id' => $this->team->id]);

        $this->actingAs($this->owner)
            ->put($this->teamRoute('company-profile.update'), $this->payload(['company_name' => 'Nama Baru']))
            ->assertRedirect();

        $this->assertSame(1, CompanyProfile::query()->where('team_id', $this->team->id)->count());

        $profile = CompanyProfile::query()->where('team_id', $this->team->id)->firstOrFail();

        $this->assertSame('Nama Baru', $profile->company_name);
    }

    public function test_nested_services_and_faq_are_persisted(): void
    {
        $this->actingAs($this->owner)
            ->put($this->teamRoute('company-profile.update'), $this->payload())
            ->assertSessionDoesntHaveErrors();

        $profile = CompanyProfile::query()->where('team_id', $this->team->id)->firstOrFail();

        $this->assertSame('srv-1', $profile->services[0]['id']);
        $this->assertSame('Pertanyaan?', $profile->faq[0]['question']);
        $this->assertSame('https://example.com', $profile->social_links['website']);
    }

    public function test_company_name_is_required(): void
    {
        $this->actingAs($this->owner)
            ->put($this->teamRoute('company-profile.update'), $this->payload(['company_name' => '']))
            ->assertSessionHasErrors('company_name');
    }

    public function test_member_of_another_team_cannot_update_the_profile(): void
    {
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->put($this->teamRoute('company-profile.update'), $this->payload())
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'company_name' => 'PT Contoh Digital',
            'description' => 'Software house',
            'about' => 'Tentang perusahaan',
            'services' => [
                ['id' => 'srv-1', 'name' => 'Custom ERP', 'desc' => 'Deskripsi', 'icon' => 'database'],
            ],
            'products' => [
                ['id' => 'prod-1', 'name' => 'WMS Barcode', 'desc' => 'Deskripsi'],
            ],
            'contact' => 'Rian Setiawan',
            'email' => 'contact@example.com',
            'phone' => '+62 812-0000-0000',
            'address' => 'Jl. Contoh No. 1',
            'social_links' => ['website' => 'https://example.com'],
            'faq' => [
                ['question' => 'Pertanyaan?', 'answer' => 'Jawaban.'],
            ],
            ...$overrides,
        ];
    }
}
