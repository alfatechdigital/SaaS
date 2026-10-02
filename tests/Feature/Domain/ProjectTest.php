<?php

namespace Tests\Feature\Domain;

use App\Models\Project;
use App\Models\Team;
use App\Models\User;

class ProjectTest extends DomainTestCase
{
    public function test_owner_can_create_a_project_scoped_to_the_team(): void
    {
        $this->actingAs($this->owner)
            ->post($this->teamRoute('projects.store'), $this->payload())
            ->assertRedirect();

        $this->assertDatabaseHas('projects', [
            'team_id' => $this->team->id,
            'name' => 'Proyek Baru',
            'status' => 'development',
        ]);
    }

    public function test_owner_can_update_a_project(): void
    {
        $project = Project::factory()->create(['team_id' => $this->team->id]);

        $this->actingAs($this->owner)
            ->put($this->teamRoute('projects.update', ['project' => $project->id]), $this->payload(['name' => 'Nama Baru']))
            ->assertRedirect();

        $this->assertSame('Nama Baru', $project->fresh()?->name);
    }

    public function test_owner_can_delete_a_project(): void
    {
        $project = Project::factory()->create(['team_id' => $this->team->id]);

        $this->actingAs($this->owner)
            ->delete($this->teamRoute('projects.destroy', ['project' => $project->id]))
            ->assertRedirect();

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    public function test_project_belonging_to_another_team_cannot_be_updated(): void
    {
        $foreign = Project::factory()->create(['team_id' => Team::factory()->create()->id]);

        $this->actingAs($this->owner)
            ->put($this->teamRoute('projects.update', ['project' => $foreign->id]), $this->payload())
            ->assertNotFound();
    }

    public function test_project_belonging_to_another_team_cannot_be_deleted(): void
    {
        $foreign = Project::factory()->create(['team_id' => Team::factory()->create()->id]);

        $this->actingAs($this->owner)
            ->delete($this->teamRoute('projects.destroy', ['project' => $foreign->id]))
            ->assertNotFound();

        $this->assertDatabaseHas('projects', ['id' => $foreign->id]);
    }

    public function test_invalid_payload_is_rejected(): void
    {
        $this->actingAs($this->owner)
            ->post($this->teamRoute('projects.store'), ['name' => '', 'progress' => 150])
            ->assertSessionHasErrors(['name', 'status', 'progress', 'project_value']);
    }

    public function test_pic_must_be_a_member_of_the_team(): void
    {
        $outsider = User::factory()->create();

        $this->actingAs($this->owner)
            ->post($this->teamRoute('projects.store'), $this->payload(['pic_id' => $outsider->id]))
            ->assertSessionHasErrors('pic_id');
    }

    public function test_creating_a_project_writes_an_activity_log(): void
    {
        $this->actingAs($this->owner)
            ->post($this->teamRoute('projects.store'), $this->payload());

        $this->assertDatabaseHas('activity_logs', [
            'team_id' => $this->team->id,
            'entity_type' => 'project',
            'performed_by_id' => $this->owner->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Proyek Baru',
            'client_name' => 'PT Contoh',
            'description' => 'Deskripsi proyek',
            'status' => 'development',
            'progress' => 25,
            'start_date' => '2025-01-01',
            'deadline' => '2025-06-30',
            'project_value' => 10_000_000,
            'pic_id' => $this->member->id,
            'technologies' => ['Laravel', 'Vue.js'],
            'notes' => 'Catatan',
            ...$overrides,
        ];
    }
}
