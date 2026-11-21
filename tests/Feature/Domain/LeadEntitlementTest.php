<?php

namespace Tests\Feature\Domain;

use App\Contracts\TeamEntitlementSource;
use App\Models\Lead;
use App\Support\Entitlements;
use Tests\Support\FakeTeamEntitlementSource;

/**
 * `lead_management` is a Plus feature; the CRM must reject Basic tenants even
 * when they reach the route directly (F-7, keputusan 2026-10-28).
 */
class LeadEntitlementTest extends DomainTestCase
{
    private function useBasicPlan(): void
    {
        $this->app->instance(TeamEntitlementSource::class, new FakeTeamEntitlementSource('basic'));
        $this->app->forgetInstance(Entitlements::class);
    }

    public function test_basic_cannot_open_the_lead_pipeline(): void
    {
        $this->useBasicPlan();

        $this->actingAs($this->owner)
            ->get($this->teamRoute('leads.index'))
            ->assertForbidden();
    }

    public function test_basic_cannot_create_update_delete_or_convert_leads(): void
    {
        $this->useBasicPlan();

        $lead = Lead::factory()->create([
            'team_id' => $this->team->id,
            'status' => 'won',
        ]);

        $this->actingAs($this->owner)
            ->post($this->teamRoute('leads.store'), $this->payload())
            ->assertForbidden();

        $this->actingAs($this->owner)
            ->put($this->teamRoute('leads.update', ['lead' => $lead->id]), $this->payload(['status' => 'lost']))
            ->assertForbidden();

        $this->actingAs($this->owner)
            ->delete($this->teamRoute('leads.destroy', ['lead' => $lead->id]))
            ->assertForbidden();

        $this->actingAs($this->owner)
            ->post($this->teamRoute('leads.convert', ['lead' => $lead->id]))
            ->assertForbidden();

        $this->assertDatabaseHas('leads', [
            'id' => $lead->id,
            'status' => 'won',
        ]);

        $this->assertDatabaseCount('projects', 0);
    }

    public function test_a_plus_plan_can_open_the_lead_pipeline(): void
    {
        $this->actingAs($this->owner)
            ->get($this->teamRoute('leads.index'))
            ->assertOk();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'company_name' => 'PT Prospek',
            'contact_name' => 'Budi',
            'phone' => '08123456789',
            'status' => 'new',
            'estimated_value' => 1000,
            'technologies' => [],
            ...$overrides,
        ];
    }
}
