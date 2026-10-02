<?php

namespace Tests\Feature\Domain;

use App\Models\Project;
use App\Models\Task;
use App\Models\Team;

class TaskTest extends DomainTestCase
{
    public function test_owner_can_create_a_task(): void
    {
        $project = Project::factory()->create(['team_id' => $this->team->id]);

        $this->actingAs($this->owner)
            ->post($this->teamRoute('tasks.store'), $this->payload($project))
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'team_id' => $this->team->id,
            'project_id' => $project->id,
            'title' => 'Tugas Baru',
            'status' => 'todo',
        ]);
    }

    public function test_owner_can_update_a_task(): void
    {
        $task = Task::factory()->create([
            'team_id' => $this->team->id,
            'project_id' => Project::factory()->create(['team_id' => $this->team->id])->id,
        ]);

        $this->actingAs($this->owner)
            ->put($this->teamRoute('tasks.update', ['task' => $task->id]), $this->payload($task->project, ['status' => 'done']))
            ->assertRedirect();

        $this->assertSame('done', $task->fresh()?->status->value);
    }

    public function test_owner_can_delete_a_task(): void
    {
        $task = Task::factory()->create([
            'team_id' => $this->team->id,
            'project_id' => Project::factory()->create(['team_id' => $this->team->id])->id,
        ]);

        $this->actingAs($this->owner)
            ->delete($this->teamRoute('tasks.destroy', ['task' => $task->id]))
            ->assertRedirect();

        $this->assertDatabaseMissing('tasks', ['id' => $task->id]);
    }

    public function test_task_cannot_reference_a_project_from_another_team(): void
    {
        $foreign = Project::factory()->create(['team_id' => Team::factory()->create()->id]);

        $this->actingAs($this->owner)
            ->post($this->teamRoute('tasks.store'), $this->payload($foreign))
            ->assertSessionHasErrors('project_id');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Project $project, array $overrides = []): array
    {
        return [
            'project_id' => $project->id,
            'title' => 'Tugas Baru',
            'description' => 'Deskripsi tugas',
            'assignee_id' => $this->member->id,
            'priority' => 'high',
            'status' => 'todo',
            'due_date' => '2025-02-01',
            ...$overrides,
        ];
    }
}
