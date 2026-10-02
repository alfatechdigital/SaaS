<?php

namespace Tests\Feature\Domain;

use App\Models\ActivityLog;
use App\Models\CompanyProfile;
use App\Models\ContentItem;
use App\Models\Lead;
use App\Models\PortfolioItem;
use App\Models\Project;
use App\Models\Task;
use App\Models\Transaction;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Guards against the JSON resource `data` wrapper leaking into Inertia props.
 *
 * Without `JsonResource::withoutWrapping()`, a nested resource collection
 * serialises to `{ data: [...] }`, so the frontend receives an object where it
 * expects an array. Asserting on an indexed path (`items.0.id`) fails when that
 * happens, which is exactly what the earlier phase 2 tests missed.
 *
 * @see \docs\IMPLEMENTATION_PLAN.md §12 temuan Fase 4
 */
class IndexPayloadShapeTest extends DomainTestCase
{
    public function test_projects_index_returns_flat_arrays(): void
    {
        $project = Project::factory()->create(['team_id' => $this->team->id]);
        Task::factory()->create(['team_id' => $this->team->id, 'project_id' => $project->id]);

        $this->actingAs($this->owner)
            ->get($this->teamRoute('projects.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/projects/Index')
                ->has('projects', 1)
                ->where('projects.0.id', $project->id)
                ->has('tasks', 1)
                ->where('tasks.0.projectId', $project->id)
                ->has('members', 2));
    }

    public function test_leads_index_returns_a_flat_array(): void
    {
        $lead = Lead::factory()->create(['team_id' => $this->team->id]);

        $this->actingAs($this->owner)
            ->get($this->teamRoute('leads.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/leads/Index')
                ->has('leads', 1)
                ->where('leads.0.id', $lead->id));
    }

    public function test_contents_index_returns_flat_arrays(): void
    {
        $content = ContentItem::factory()->create(['team_id' => $this->team->id]);

        $this->actingAs($this->owner)
            ->get($this->teamRoute('contents.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/content/Index')
                ->has('contents', 1)
                ->where('contents.0.id', $content->id)
                ->has('members', 2));
    }

    public function test_transactions_index_returns_flat_arrays(): void
    {
        $transaction = Transaction::factory()->create(['team_id' => $this->team->id]);

        $this->actingAs($this->owner)
            ->get($this->teamRoute('transactions.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/finance/Index')
                ->has('transactions', 1)
                ->where('transactions.0.id', $transaction->id)
                ->has('projects')
                ->has('totals.income')
                ->has('totals.expense'));
    }

    public function test_portfolio_index_returns_a_flat_array(): void
    {
        $item = PortfolioItem::factory()->create(['team_id' => $this->team->id]);

        $this->actingAs($this->owner)
            ->get($this->teamRoute('portfolio.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/portfolio/Index')
                ->has('items', 1)
                ->where('items.0.id', $item->id));
    }

    public function test_activity_logs_index_returns_a_flat_array(): void
    {
        ActivityLog::factory()->create(['team_id' => $this->team->id]);

        $this->actingAs($this->owner)
            ->get($this->teamRoute('activity-logs.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/activity-logs/Index')
                ->has('logs', 1));
    }

    public function test_company_profile_edit_returns_a_flat_object(): void
    {
        CompanyProfile::factory()->create(['team_id' => $this->team->id]);

        $this->actingAs($this->owner)
            ->get($this->teamRoute('company-profile.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/company-profile/Edit')
                ->where('profile.companyName', fn ($name) => is_string($name))
                ->has('profile.services')
                ->has('profile.faq'));
    }
}
