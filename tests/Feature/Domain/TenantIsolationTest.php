<?php

namespace Tests\Feature\Domain;

use Illuminate\Database\Eloquent\Model;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * P-1 — tenant isolation suite for every `index` (read) endpoint.
 *
 * Part of the "jaring pengaman" that has to exist *before* the P-2 global scope
 * is introduced: without it, there is no way to tell a regression caused by that
 * change apart from a leak that was already there.
 *
 * Every case seeds two tenants through {@see TenantIsolationTestCase::seedLookAlikeData()},
 * so both hold rows with identical names. Each tenant owns exactly one row per
 * module; therefore a correctly scoped `index` returns exactly one row — the
 * acting tenant's own — and its `id` is the only value that can prove it.
 *
 * The `forTeam()` scoping that these tests guard is applied manually in
 * controllers, so they are expected to fail the moment that call is removed.
 *
 * @see docs/syarhul-implementation-urgent.md P-1
 * @see docs/implementation/phase-01-fondasi.md tugas 1.2.2
 */
class TenantIsolationTest extends TenantIsolationTestCase
{
    /**
     * Guard: keep the fixture honest.
     *
     * If the two tenants ever stop being look-alikes, every other test in this
     * file would silently lose its meaning.
     */
    public function test_the_two_tenants_hold_look_alike_data(): void
    {
        $mine = $this->seedLookAlikeData($this->team);
        $theirs = $this->seedLookAlikeData($this->otherTeam);

        $this->assertSame($mine['company_profile']->company_name, $theirs['company_profile']->company_name);
        $this->assertSame($mine['project']->name, $theirs['project']->name);
        $this->assertSame($mine['task']->title, $theirs['task']->title);
        $this->assertSame($mine['lead']->company_name, $theirs['lead']->company_name);
        $this->assertSame($mine['content']->title, $theirs['content']->title);
        $this->assertSame($mine['transaction']->description, $theirs['transaction']->description);
        $this->assertSame($mine['portfolio']->title, $theirs['portfolio']->title);

        $this->assertNotSame($mine['project']->id, $theirs['project']->id);
        $this->assertNotSame($mine['company_profile']->id, $theirs['company_profile']->id);
    }

    public function test_projects_index_only_returns_the_active_tenant_projects(): void
    {
        $mine = $this->seedLookAlikeData($this->team);
        $theirs = $this->seedLookAlikeData($this->otherTeam);

        $this->actingAs($this->owner)
            ->get($this->routeFor($this->team, 'projects.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/projects/Index')
                ->has('projects', 1)
                ->where('projects.0.id', $mine['project']->id)
                // Same name in both tenants — only the id keeps them apart.
                ->where('projects.0.name', $theirs['project']->name));
    }

    /**
     * Tasks have no route of their own (`TaskController` only has store/update/
     * destroy); they are listed on the project board. So the `tasks` prop of
     * `projects.index` is the endpoint under test here.
     */
    public function test_project_board_only_exposes_the_active_tenant_tasks(): void
    {
        $mine = $this->seedLookAlikeData($this->team);
        $theirs = $this->seedLookAlikeData($this->otherTeam);

        $this->actingAs($this->owner)
            ->get($this->routeFor($this->team, 'projects.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/projects/Index')
                ->has('tasks', 1)
                ->where('tasks.0.id', $mine['task']->id)
                ->where('tasks.0.projectId', $mine['project']->id)
                ->where('tasks.0.title', $theirs['task']->title));
    }

    public function test_leads_index_only_returns_the_active_tenant_leads(): void
    {
        $mine = $this->seedLookAlikeData($this->team);
        $theirs = $this->seedLookAlikeData($this->otherTeam);

        $this->actingAs($this->owner)
            ->get($this->routeFor($this->team, 'leads.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/leads/Index')
                ->has('leads', 1)
                ->where('leads.0.id', $mine['lead']->id)
                ->where('leads.0.companyName', $theirs['lead']->company_name));
    }

    public function test_contents_index_only_returns_the_active_tenant_contents(): void
    {
        $mine = $this->seedLookAlikeData($this->team);
        $theirs = $this->seedLookAlikeData($this->otherTeam);

        $this->actingAs($this->owner)
            ->get($this->routeFor($this->team, 'contents.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/content/Index')
                ->has('contents', 1)
                ->where('contents.0.id', $mine['content']->id)
                ->where('contents.0.title', $theirs['content']->title));
    }

    public function test_transactions_index_only_returns_the_active_tenant_transactions(): void
    {
        $mine = $this->seedLookAlikeData($this->team);
        $theirs = $this->seedLookAlikeData($this->otherTeam);

        $this->actingAs($this->owner)
            ->get($this->routeFor($this->team, 'transactions.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/finance/Index')
                ->has('transactions', 1)
                ->where('transactions.0.id', $mine['transaction']->id)
                ->where('transactions.0.description', $theirs['transaction']->description)
                ->where('projects', fn (mixed $projects): bool => is_iterable($projects)
                    && collect($projects)->pluck('id')->map(static fn ($id): int => (int) $id)->all() === [$mine['project']->id]));
    }

    public function test_portfolio_index_only_returns_the_active_tenant_items(): void
    {
        $mine = $this->seedLookAlikeData($this->team);
        $theirs = $this->seedLookAlikeData($this->otherTeam);

        $this->actingAs($this->owner)
            ->get($this->routeFor($this->team, 'portfolio.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/portfolio/Index')
                ->has('items', 1)
                ->where('items.0.id', $mine['portfolio']->id)
                ->where('items.0.title', $theirs['portfolio']->title));
    }

    public function test_activity_logs_index_only_returns_the_active_tenant_logs(): void
    {
        $mine = $this->seedLookAlikeData($this->team);
        $this->seedLookAlikeData($this->otherTeam);

        // One log per observed model, both tenants seeded identically: a correct
        // scope returns the acting tenant's 7 logs and nothing from the other.
        $expectedIds = collect($mine)
            ->map(static fn (Model $model): int => (int) $model->getKey())
            ->sort()
            ->values()
            ->all();

        $this->actingAs($this->owner)
            ->get($this->routeFor($this->team, 'activity-logs.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/activity-logs/Index')
                ->has('logs', 7)
                ->where('logs', fn (mixed $logs): bool => $this->entityIdsMatch($logs, $expectedIds)));
    }

    public function test_company_profile_edit_only_returns_the_active_tenant_profile(): void
    {
        $mine = $this->seedLookAlikeData($this->team);
        $theirs = $this->seedLookAlikeData($this->otherTeam);

        $this->actingAs($this->owner)
            ->get($this->routeFor($this->team, 'company-profile.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/company-profile/Edit')
                ->where('profile.id', $mine['company_profile']->id)
                ->where('profile.companyName', $theirs['company_profile']->company_name));
    }

    /**
     * Assert that the returned log rows reference exactly the expected entity ids.
     *
     * @param  list<int>  $expected
     */
    private function entityIdsMatch(mixed $logs, array $expected): bool
    {
        if (! is_iterable($logs)) {
            return false;
        }

        $ids = collect($logs)
            ->pluck('entityId')
            ->map(static fn ($id): int => (int) $id)
            ->sort()
            ->values()
            ->all();

        return $ids === $expected;
    }
}
