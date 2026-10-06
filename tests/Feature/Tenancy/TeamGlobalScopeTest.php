<?php

namespace Tests\Feature\Tenancy;

use App\Exceptions\MissingTenantContext;
use App\Models\ActivityLog;
use App\Models\CompanyProfile;
use App\Models\ContentItem;
use App\Models\Lead;
use App\Models\PortfolioItem;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\Transaction;
use App\Scopes\TeamScope;
use App\Support\TenantContext;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * P-2 / tugas 2.2.1 s/d 2.2.7 — the team global scope.
 *
 * The isolation suite proves the scope end-to-end through HTTP; this file proves
 * the mechanics underneath it: a query without a tenant is refused, a query with
 * one is confined to it, and the escape hatch really escapes.
 *
 * Every tenant-owned model is covered: Fase 2 switches the scope on per model,
 * and this file is the per-model proof that the switch actually took effect.
 *
 * tugas 2.2.4 — audit of every `withoutTeamScope()` call site. Each one below
 * carries a comment saying why the query has to leave the tenant, which is what
 * makes the escape hatch auditable instead of convenient.
 *
 * @see TeamScope
 * @see \docs\implementation\phase-02-tenant-context.md tugas 2.2.1 s/d 2.2.7
 */
class TeamGlobalScopeTest extends TestCase
{
    use RefreshDatabase;

    private TenantContext $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = app(TenantContext::class);
    }

    /**
     * Every model already protected by the team global scope, with its table name
     * (asserted through the query builder, which ignores the scope) and a way to
     * give it one row for a tenant.
     *
     * @return array<string, array{0: class-string<Model>, 1: string, 2: Closure(Team): Model}>
     */
    public static function tenantModels(): array
    {
        return [
            'company_profiles' => [
                CompanyProfile::class,
                'company_profiles',
                static fn (Team $team): Model => CompanyProfile::factory()->create(['team_id' => $team->id]),
            ],
            'projects' => [
                Project::class,
                'projects',
                static fn (Team $team): Model => Project::factory()->create(['team_id' => $team->id]),
            ],
            'tasks' => [
                Task::class,
                'tasks',
                static fn (Team $team): Model => Task::factory()->create([
                    'team_id' => $team->id,
                    'project_id' => Project::factory()->create(['team_id' => $team->id])->id,
                ]),
            ],
            'leads' => [
                Lead::class,
                'leads',
                static fn (Team $team): Model => Lead::factory()->create(['team_id' => $team->id]),
            ],
            'content_items' => [
                ContentItem::class,
                'content_items',
                static fn (Team $team): Model => ContentItem::factory()->create(['team_id' => $team->id]),
            ],
            'transactions' => [
                Transaction::class,
                'transactions',
                static fn (Team $team): Model => Transaction::factory()->income()->create(['team_id' => $team->id]),
            ],
            'portfolio_items' => [
                PortfolioItem::class,
                'portfolio_items',
                static fn (Team $team): Model => PortfolioItem::factory()->create(['team_id' => $team->id]),
            ],
            'activity_logs' => [
                ActivityLog::class,
                'activity_logs',
                static fn (Team $team): Model => ActivityLog::factory()->create(['team_id' => $team->id]),
            ],
        ];
    }

    /**
     * @param  class-string<Model>  $model
     * @param  Closure(Team): Model  $make
     */
    #[DataProvider('tenantModels')]
    public function test_queries_fail_loudly_without_an_active_tenant(string $model, string $table, Closure $make): void
    {
        // A row exists, so "no rows returned" would look like a legitimate empty
        // screen. Failing loudly is the difference between a bug and a leak.
        $make(Team::factory()->create());

        $this->assertDatabaseCount($table, 1);

        $this->expectException(MissingTenantContext::class);

        $model::query()->count();
    }

    /**
     * @param  class-string<Model>  $model
     * @param  Closure(Team): Model  $make
     */
    #[DataProvider('tenantModels')]
    public function test_queries_are_confined_to_the_active_tenant(string $model, string $table, Closure $make): void
    {
        $mineTeam = Team::factory()->create();
        $mine = $make($mineTeam);
        $make(Team::factory()->create());

        // Two rows really exist; the context, not luck, is what hides one.
        $this->assertDatabaseCount($table, 2);

        $this->context->runFor($mineTeam, function () use ($model, $mine): void {
            $this->assertSame(1, $model::query()->count());
            $this->assertSame($mine->getKey(), $model::query()->first()?->getKey());
        });
    }

    public function test_the_exception_names_the_model_that_was_queried(): void
    {
        $this->expectException(MissingTenantContext::class);
        $this->expectExceptionMessage(Project::class);

        Project::query()->count();
    }

    public function test_without_team_scope_reaches_every_tenant(): void
    {
        Project::factory()->create(['team_id' => Team::factory()->create()->id]);
        Project::factory()->create(['team_id' => Team::factory()->create()->id]);

        // Reason for the escape hatch: cross-tenant work such as the platform
        // layer must be able to opt out of the scope. No tenant is active here,
        // which is exactly the point.
        $this->assertNull($this->context->team());
        $this->assertSame(2, Project::withoutTeamScope()->count());
    }

    public function test_without_team_scope_escapes_an_active_tenant(): void
    {
        $mine = Project::factory()->create(['team_id' => Team::factory()->create()->id]);
        $theirs = Project::factory()->create(['team_id' => Team::factory()->create()->id]);

        $this->context->runFor($mine->team, function () use ($theirs): void {
            $this->assertSame(1, Project::query()->count());

            // Reason for the escape hatch: this is the only way to reach a row
            // that belongs to another tenant, and that has to be a deliberate,
            // commented act rather than an accident.
            $this->assertSame(2, Project::query()->withoutTeamScope()->count());
            $this->assertTrue(
                Project::query()->withoutTeamScope()->whereKey($theirs->id)->exists(),
            );
        });
    }

    /**
     * The scope is the authority, so an explicit `forTeam()` cannot widen it.
     *
     * Worth pinning down: it is what allowed tugas 2.2.6 to delete the `->forTeam()`
     * calls that had become redundant, and why a stray one stays harmless.
     */
    public function test_for_team_cannot_widen_the_active_tenant(): void
    {
        $mine = Project::factory()->create(['team_id' => Team::factory()->create()->id]);
        $otherTeam = Team::factory()->create();
        Project::factory()->create(['team_id' => $otherTeam->id]);

        $this->context->runFor($mine->team, function () use ($otherTeam, $mine): void {
            $this->assertSame(1, Project::query()->forTeam($mine->team)->count());
            $this->assertSame(0, Project::query()->forTeam($otherTeam)->count());
        });
    }
}
