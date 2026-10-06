<?php

namespace Tests\Feature\Domain;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * P-1 — tenant isolation suite: every read endpoint, every cross-tenant write id
 * (IDOR, R-4) and the membership boundary.
 *
 * Part of the "jaring pengaman" that has to exist *before* the P-2 global scope
 * is introduced: without it, there is no way to tell a regression caused by that
 * change apart from a leak that was already there.
 *
 * Every case seeds two tenants through {@see TenantIsolationTestCase::seedLookAlikeData()},
 * so both hold rows with identical names. Each tenant owns exactly one row per
 * module; therefore a correctly scoped endpoint only ever exposes — or accepts —
 * the acting tenant's own row, and its `id` is the only value that can prove it.
 *
 * The confinement these tests guard comes from the team global scope
 * (`App\Scopes\TeamScope`), not from an explicit filter in the controller: the
 * scope refuses a query with no active tenant and confines the rest to the
 * active one, so a stray `->forTeam()` naming another team can never widen it.
 * Tugas 2.2.6 removed those redundant calls; this suite is the proof that the
 * scope, not the calls, is what isolates the tenants.
 *
 * @see docs/syarhul-implementation-urgent.md P-1
 * @see docs/implementation/phase-01-fondasi.md tugas 1.2.2, 1.2.3, 1.2.4
 * @see docs/implementation/phase-02-tenant-context.md tugas 2.2.7
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

    /*
    |--------------------------------------------------------------------------
    | 2.2.7 — the widest single read in the application
    |--------------------------------------------------------------------------
    */

    /**
     * The dashboard aggregates all five tenant-owned modules in one response, so
     * it is the broadest read the application performs.
     *
     * Tugas 2.2.6 removed the five `->forTeam()` calls from `DashboardController`.
     * This test pins the behaviour down: confinement now comes from the team
     * global scope alone, and every count below would double without it.
     */
    public function test_dashboard_only_exposes_the_active_tenant_data(): void
    {
        $mine = $this->seedLookAlikeData($this->team);
        $this->seedLookAlikeData($this->otherTeam);

        $mineIds = collect($mine)
            ->map(static fn (Model $model): int => (int) $model->getKey())
            ->all();

        $this->actingAs($this->owner)
            ->get($this->routeFor($this->team, 'dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/dashboard/Index')
                ->has('projects', 1)
                ->where('projects.0.id', $mine['project']->id)
                ->has('leads', 1)
                ->where('leads.0.id', $mine['lead']->id)
                ->has('contents', 1)
                ->where('contents.0.id', $mine['content']->id)
                ->has('transactions', 1)
                ->where('transactions.0.id', $mine['transaction']->id)
                // Each tenant owns seven logs and the dashboard renders the five
                // most recent, so a count proves nothing on its own — every
                // rendered row has to reference one of the acting tenant's records.
                ->has('activityLogs', 5)
                ->where('activityLogs', fn (mixed $logs): bool => is_iterable($logs)
                    && collect($logs)->every(
                        static fn ($log): bool => in_array((int) $log['entityId'], $mineIds, true),
                    )));
    }

    /*
    |--------------------------------------------------------------------------
    | 1.2.3 — write endpoints must not accept another tenant's id (IDOR, R-4)
    |--------------------------------------------------------------------------
    |
    | Every request below targets the acting tenant's own route but carries the
    | other tenant's record id, with a payload that passes validation. The
    | scope has to reject it with 404 — and the row must be left untouched.
    |
    */

    public function test_a_project_of_another_tenant_cannot_be_updated(): void
    {
        $theirs = $this->seedLookAlikeData($this->otherTeam);

        $this->actingAs($this->owner)
            ->put($this->routeFor($this->team, 'projects.update', ['project' => $theirs['project']->id]), $this->projectPayload())
            ->assertNotFound();

        $this->assertDatabaseHas('projects', ['id' => $theirs['project']->id, 'name' => 'Proyek Kembar']);
    }

    public function test_a_project_of_another_tenant_cannot_be_deleted(): void
    {
        $theirs = $this->seedLookAlikeData($this->otherTeam);

        $this->actingAs($this->owner)
            ->delete($this->routeFor($this->team, 'projects.destroy', ['project' => $theirs['project']->id]))
            ->assertNotFound();

        $this->assertDatabaseHas('projects', ['id' => $theirs['project']->id]);
    }

    public function test_a_task_of_another_tenant_cannot_be_updated(): void
    {
        $mine = $this->seedLookAlikeData($this->team);
        $theirs = $this->seedLookAlikeData($this->otherTeam);

        $this->actingAs($this->owner)
            ->put(
                $this->routeFor($this->team, 'tasks.update', ['task' => $theirs['task']->id]),
                $this->taskPayload($mine['project']),
            )
            ->assertNotFound();

        $this->assertDatabaseHas('tasks', ['id' => $theirs['task']->id, 'title' => 'Tugas Kembar']);
    }

    public function test_a_task_of_another_tenant_cannot_be_deleted(): void
    {
        $theirs = $this->seedLookAlikeData($this->otherTeam);

        $this->actingAs($this->owner)
            ->delete($this->routeFor($this->team, 'tasks.destroy', ['task' => $theirs['task']->id]))
            ->assertNotFound();

        $this->assertDatabaseHas('tasks', ['id' => $theirs['task']->id]);
    }

    public function test_a_lead_of_another_tenant_cannot_be_updated(): void
    {
        $theirs = $this->seedLookAlikeData($this->otherTeam);

        $this->actingAs($this->owner)
            ->put($this->routeFor($this->team, 'leads.update', ['lead' => $theirs['lead']->id]), $this->leadPayload())
            ->assertNotFound();

        $this->assertDatabaseHas('leads', ['id' => $theirs['lead']->id, 'company_name' => 'PT Prospek Kembar']);
    }

    public function test_a_lead_of_another_tenant_cannot_be_deleted(): void
    {
        $theirs = $this->seedLookAlikeData($this->otherTeam);

        $this->actingAs($this->owner)
            ->delete($this->routeFor($this->team, 'leads.destroy', ['lead' => $theirs['lead']->id]))
            ->assertNotFound();

        $this->assertDatabaseHas('leads', ['id' => $theirs['lead']->id]);
    }

    public function test_a_content_item_of_another_tenant_cannot_be_updated(): void
    {
        $theirs = $this->seedLookAlikeData($this->otherTeam);

        $this->actingAs($this->owner)
            ->put($this->routeFor($this->team, 'contents.update', ['content' => $theirs['content']->id]), $this->contentPayload())
            ->assertNotFound();

        $this->assertDatabaseHas('content_items', ['id' => $theirs['content']->id, 'title' => 'Konten Kembar']);
    }

    public function test_a_content_item_of_another_tenant_cannot_be_deleted(): void
    {
        $theirs = $this->seedLookAlikeData($this->otherTeam);

        $this->actingAs($this->owner)
            ->delete($this->routeFor($this->team, 'contents.destroy', ['content' => $theirs['content']->id]))
            ->assertNotFound();

        $this->assertDatabaseHas('content_items', ['id' => $theirs['content']->id]);
    }

    public function test_a_transaction_of_another_tenant_cannot_be_updated(): void
    {
        $theirs = $this->seedLookAlikeData($this->otherTeam);

        $this->actingAs($this->owner)
            ->put($this->routeFor($this->team, 'transactions.update', ['transaction' => $theirs['transaction']->id]), $this->transactionPayload())
            ->assertNotFound();

        $this->assertDatabaseHas('transactions', ['id' => $theirs['transaction']->id, 'description' => 'Transaksi Kembar']);
    }

    public function test_a_transaction_of_another_tenant_cannot_be_deleted(): void
    {
        $theirs = $this->seedLookAlikeData($this->otherTeam);

        $this->actingAs($this->owner)
            ->delete($this->routeFor($this->team, 'transactions.destroy', ['transaction' => $theirs['transaction']->id]))
            ->assertNotFound();

        $this->assertDatabaseHas('transactions', ['id' => $theirs['transaction']->id]);
    }

    public function test_a_portfolio_item_of_another_tenant_cannot_be_updated(): void
    {
        $theirs = $this->seedLookAlikeData($this->otherTeam);

        $this->actingAs($this->owner)
            ->put($this->routeFor($this->team, 'portfolio.update', ['portfolioItem' => $theirs['portfolio']->id]), $this->portfolioPayload())
            ->assertNotFound();

        $this->assertDatabaseHas('portfolio_items', ['id' => $theirs['portfolio']->id, 'title' => 'Portfolio Kembar']);
    }

    public function test_a_portfolio_item_of_another_tenant_cannot_be_deleted(): void
    {
        $theirs = $this->seedLookAlikeData($this->otherTeam);

        $this->actingAs($this->owner)
            ->delete($this->routeFor($this->team, 'portfolio.destroy', ['portfolioItem' => $theirs['portfolio']->id]))
            ->assertNotFound();

        $this->assertDatabaseHas('portfolio_items', ['id' => $theirs['portfolio']->id]);
    }

    /*
    |--------------------------------------------------------------------------
    | 1.2.4 — membership boundary
    |--------------------------------------------------------------------------
    */

    public function test_a_member_of_another_tenant_cannot_open_the_dashboard(): void
    {
        $this->actingAs($this->otherOwner)
            ->get($this->routeFor($this->team, 'dashboard'))
            ->assertForbidden();
    }

    public function test_a_user_without_any_tenant_cannot_open_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create())
            ->get($this->routeFor($this->team, 'dashboard'))
            ->assertForbidden();
    }

    public function test_a_guest_cannot_open_the_dashboard(): void
    {
        $this->get($this->routeFor($this->team, 'dashboard'))
            ->assertRedirect();
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
