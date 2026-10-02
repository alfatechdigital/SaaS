<?php

namespace Tests\Feature\Domain;

use App\Models\Project;
use App\Models\Team;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Covers the activity log payload, including the `actionType` the timeline uses
 * for its badge colours.
 */
class ActivityLogTest extends DomainTestCase
{
    public function test_the_index_returns_a_flat_array(): void
    {
        Project::factory()->create(['team_id' => $this->team->id]);

        $this->actingAs($this->owner)
            ->get($this->teamRoute('activity-logs.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/activity-logs/Index')
                ->has('logs.0.id')
                ->has('logs.0.details'));
    }

    public function test_a_created_record_is_classified_as_created(): void
    {
        Project::factory()->create(['team_id' => $this->team->id, 'name' => 'Proyek Audit']);

        $this->actingAs($this->owner)
            ->get($this->teamRoute('activity-logs.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('logs.0.actionType', 'created')
                ->where('logs.0.action', 'Menambahkan proyek')
                ->where('logs.0.entityType', 'project')
                ->where('logs.0.details', 'Proyek ditambahkan: Proyek Audit'));
    }

    public function test_an_updated_record_is_classified_as_updated(): void
    {
        $project = Project::factory()->create(['team_id' => $this->team->id]);

        $project->update(['name' => 'Nama Baru']);

        $this->actingAs($this->owner)
            ->get($this->teamRoute('activity-logs.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('logs.0.actionType', 'updated')
                ->where('logs.0.action', 'Memperbarui proyek'));
    }

    public function test_a_deleted_record_is_classified_as_deleted(): void
    {
        $project = Project::factory()->create(['team_id' => $this->team->id]);

        $project->delete();

        $this->actingAs($this->owner)
            ->get($this->teamRoute('activity-logs.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('logs.0.actionType', 'deleted')
                ->where('logs.0.action', 'Menghapus proyek'));
    }

    public function test_activity_from_another_team_is_not_exposed(): void
    {
        $otherTeam = Team::factory()->create();
        Project::factory()->create(['team_id' => $otherTeam->id]);

        $this->actingAs($this->owner)
            ->get($this->teamRoute('activity-logs.index'))
            ->assertInertia(fn (Assert $page) => $page->has('logs', 0));
    }
}
