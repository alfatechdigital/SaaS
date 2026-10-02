<?php

namespace Tests\Feature\Domain;

use App\Models\Lead;
use App\Models\Project;
use App\Models\Team;

class LeadTest extends DomainTestCase
{
    public function test_owner_can_create_a_lead(): void
    {
        $this->actingAs($this->owner)
            ->post($this->teamRoute('leads.store'), $this->payload())
            ->assertRedirect();

        $this->assertDatabaseHas('leads', [
            'team_id' => $this->team->id,
            'company_name' => 'PT Prospek',
            'status' => 'new',
        ]);
    }

    public function test_owner_can_update_a_lead(): void
    {
        $lead = Lead::factory()->create(['team_id' => $this->team->id]);

        $this->actingAs($this->owner)
            ->put($this->teamRoute('leads.update', ['lead' => $lead->id]), $this->payload(['status' => 'won']))
            ->assertRedirect();

        $this->assertSame('won', $lead->fresh()?->status->value);
    }

    public function test_owner_can_delete_a_lead(): void
    {
        $lead = Lead::factory()->create(['team_id' => $this->team->id]);

        $this->actingAs($this->owner)
            ->delete($this->teamRoute('leads.destroy', ['lead' => $lead->id]))
            ->assertRedirect();

        $this->assertDatabaseMissing('leads', ['id' => $lead->id]);
    }

    public function test_estimated_value_cannot_be_negative(): void
    {
        $this->actingAs($this->owner)
            ->post($this->teamRoute('leads.store'), $this->payload(['estimated_value' => -1]))
            ->assertSessionHasErrors('estimated_value');
    }

    public function test_free_text_follow_up_is_accepted(): void
    {
        $this->actingAs($this->owner)
            ->post($this->teamRoute('leads.store'), $this->payload(['next_follow_up' => 'Besok pagi']))
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('leads', ['next_follow_up' => 'Besok pagi']);
    }

    public function test_won_lead_can_be_converted_into_a_project(): void
    {
        $lead = Lead::factory()->create([
            'team_id' => $this->team->id,
            'company_name' => 'PT Menang',
            'potential_project' => 'Sistem ERP',
            'estimated_value' => 42_000_000,
            'status' => 'won',
        ]);

        $this->actingAs($this->owner)
            ->post($this->teamRoute('leads.convert', ['lead' => $lead->id]))
            ->assertRedirect($this->teamRoute('projects.index'));

        $this->assertDatabaseHas('projects', [
            'team_id' => $this->team->id,
            'name' => 'Sistem ERP',
            'client_name' => 'PT Menang',
            'status' => 'deal',
            'project_value' => 42_000_000,
            'pic_id' => $this->owner->id,
        ]);
    }

    public function test_only_won_leads_can_be_converted(): void
    {
        $lead = Lead::factory()->create([
            'team_id' => $this->team->id,
            'status' => 'negotiation',
        ]);

        $this->actingAs($this->owner)
            ->post($this->teamRoute('leads.convert', ['lead' => $lead->id]))
            ->assertStatus(422);

        $this->assertDatabaseCount('projects', 0);
    }

    public function test_lead_from_another_team_cannot_be_converted(): void
    {
        $otherTeam = Team::factory()->create();
        $lead = Lead::factory()->create(['team_id' => $otherTeam->id, 'status' => 'won']);

        $this->actingAs($this->owner)
            ->post($this->teamRoute('leads.convert', ['lead' => $lead->id]))
            ->assertNotFound();

        $this->assertDatabaseCount('projects', 0);
    }

    public function test_convert_falls_back_to_a_generated_project_name(): void
    {
        $lead = Lead::factory()->create([
            'team_id' => $this->team->id,
            'company_name' => 'PT Tanpa Kebutuhan',
            'potential_project' => null,
            'status' => 'won',
        ]);

        $this->actingAs($this->owner)
            ->post($this->teamRoute('leads.convert', ['lead' => $lead->id]))
            ->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'team_id' => $this->team->id,
            'name' => 'Proyek PT Tanpa Kebutuhan',
        ]);
    }

    public function test_convert_records_the_source_lead(): void
    {
        $lead = Lead::factory()->create(['team_id' => $this->team->id, 'status' => 'won']);

        $this->actingAs($this->owner)
            ->post($this->teamRoute('leads.convert', ['lead' => $lead->id]))
            ->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'team_id' => $this->team->id,
            'lead_id' => $lead->id,
        ]);
    }

    public function test_a_lead_cannot_be_converted_twice(): void
    {
        $lead = Lead::factory()->create(['team_id' => $this->team->id, 'status' => 'won']);

        $this->actingAs($this->owner)
            ->post($this->teamRoute('leads.convert', ['lead' => $lead->id]))
            ->assertRedirect();

        $this->actingAs($this->owner)
            ->post($this->teamRoute('leads.convert', ['lead' => $lead->id]))
            ->assertRedirect();

        $this->assertDatabaseCount('projects', 1);
    }

    public function test_unrelated_projects_can_share_a_null_lead(): void
    {
        Project::factory()->count(3)->create(['team_id' => $this->team->id, 'lead_id' => null]);

        $this->assertDatabaseCount('projects', 3);
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
            'phone' => '0812-0000-0000',
            'email' => 'budi@example.com',
            'source' => 'Website Alfatech',
            'potential_project' => 'Portal Investasi',
            'estimated_value' => 5_000_000,
            'status' => 'new',
            'next_follow_up' => 'Hari ini',
            'notes' => 'Catatan',
            ...$overrides,
        ];
    }
}
