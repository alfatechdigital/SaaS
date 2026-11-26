<?php

namespace Tests\Feature\Domain;

use App\Models\Project;

/**
 * The plan matrix is enforced in the controllers, not only in the menu (Fase 4
 * RF4-1; tugas 25 Nov). Modules that moved to a higher plan must answer 403 even
 * when the URL is typed by hand, and must stay usable for the plan that pays.
 */
class ModuleEntitlementTest extends DomainTestCase
{
    public function test_basic_cannot_open_the_pro_modules(): void
    {
        $this->usePlan('basic');

        foreach (['projects.index', 'contents.index', 'transactions.index'] as $name) {
            $this->actingAs($this->owner)
                ->get($this->teamRoute($name))
                ->assertForbidden();
        }
    }

    public function test_basic_cannot_write_to_a_pro_module(): void
    {
        $this->usePlan('basic');

        $this->actingAs($this->owner)
            ->post($this->teamRoute('projects.store'), $this->projectPayload())
            ->assertForbidden();

        $this->actingAs($this->owner)
            ->post($this->teamRoute('contents.store'), $this->contentPayload())
            ->assertForbidden();

        $this->actingAs($this->owner)
            ->post($this->teamRoute('transactions.store'), $this->transactionPayload())
            ->assertForbidden();

        $this->assertDatabaseCount('projects', 0);
        $this->assertDatabaseCount('content_items', 0);
        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_plus_keeps_its_own_modules_but_not_the_pro_ones(): void
    {
        $this->usePlan('plus');

        $this->actingAs($this->owner)
            ->get($this->teamRoute('projects.index'))
            ->assertForbidden();

        $this->actingAs($this->owner)
            ->get($this->teamRoute('contents.index'))
            ->assertForbidden();

        $this->actingAs($this->owner)
            ->get($this->teamRoute('transactions.index'))
            ->assertForbidden();

        // Company profile and portfolio are part of every plan.
        $this->actingAs($this->owner)
            ->get($this->teamRoute('company-profile.edit'))
            ->assertOk();

        $this->actingAs($this->owner)
            ->get($this->teamRoute('portfolio.index'))
            ->assertOk();
    }

    public function test_pro_can_open_every_module(): void
    {
        foreach (['projects.index', 'contents.index', 'transactions.index', 'portfolio.index', 'company-profile.edit'] as $name) {
            $this->actingAs($this->owner)
                ->get($this->teamRoute($name))
                ->assertOk();
        }
    }

    public function test_pro_can_still_write_a_task(): void
    {
        $project = Project::factory()->create(['team_id' => $this->team->id]);

        $this->actingAs($this->owner)
            ->post($this->teamRoute('tasks.store'), [
                'project_id' => $project->id,
                'title' => 'Tugas Baru',
                'description' => 'Deskripsi tugas',
                'assignee_id' => $this->member->id,
                'priority' => 'high',
                'status' => 'todo',
                'due_date' => '2026-02-01',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', [
            'team_id' => $this->team->id,
            'title' => 'Tugas Baru',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function projectPayload(): array
    {
        return [
            'name' => 'Proyek Baru',
            'client_name' => 'Klien',
            'status' => 'development',
            'progress' => 10,
            'project_value' => 10_000_000,
            'pic_id' => $this->member->id,
            'technologies' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function contentPayload(): array
    {
        return [
            'title' => 'Konten Baru',
            'platform' => 'instagram',
            'content_type' => 'Reels / Carousel',
            'status' => 'draft',
            'assignee_id' => $this->member->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transactionPayload(): array
    {
        return [
            'category' => 'project_income',
            'description' => 'Pembayaran termin 1',
            'amount' => 1_500_000,
            'date' => '2026-01-15',
        ];
    }
}
