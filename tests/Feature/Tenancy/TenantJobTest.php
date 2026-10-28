<?php

namespace Tests\Feature\Tenancy;

use App\Enums\TenantContextOrigin;
use App\Jobs\TenantJob;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Support\TenantCache;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Tugas 2.6.2 — the tenant-aware job pattern.
 *
 * There is no production job yet, so this pins the *pattern*: a job carries
 * `team_id`, `handle()` re-establishes the context, and everything a tenant job
 * touches (scoped queries, cache) works inside it and only inside it.
 *
 * @see \docs\implementation\phase-02-tenant-context.md tugas 2.6.2
 */
class TenantJobTest extends TestCase
{
    use RefreshDatabase;

    private TenantContext $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = app(TenantContext::class);

        ProbeTenantJob::$runs = [];
        ProbeTenantJob::$projectIds = [];
        ProbeTenantJob::$cacheKeys = [];
    }

    public function test_the_job_payload_carries_the_tenant_id(): void
    {
        $payload = unserialize(serialize(new ProbeTenantJob(42)));

        $this->assertInstanceOf(ProbeTenantJob::class, $payload);
        $this->assertSame(42, $payload->teamId);
    }

    public function test_the_job_runs_with_its_own_tenant_active(): void
    {
        [$team, $project] = $this->tenantWithProject();

        (new ProbeTenantJob($team->id))->handle();

        $this->assertSame([$team->id], ProbeTenantJob::$runs);

        // A tenant-scoped query succeeded inside the job: the global scope saw the
        // job's tenant, not the empty context the worker starts with.
        $this->assertSame([$project->id], ProbeTenantJob::$projectIds);
    }

    public function test_dispatching_through_the_queue_runs_the_job_with_its_tenant(): void
    {
        [$team, $project] = $this->tenantWithProject();

        ProbeTenantJob::dispatch($team->id);

        $this->assertSame([$team->id], ProbeTenantJob::$runs);
        $this->assertSame([$project->id], ProbeTenantJob::$projectIds);
    }

    public function test_the_tenant_and_the_log_context_are_cleared_afterwards(): void
    {
        [$team] = $this->tenantWithProject();

        $contexts = [];
        Log::listen(function (MessageLogged $event) use (&$contexts): void {
            $contexts[] = $event->context;
        });

        (new ProbeTenantJob($team->id))->handle();

        Log::info('after the job');

        // runFor() restores what it found — in a worker that is "no tenant", so
        // the next job cannot inherit this one's team_id in its log lines.
        $this->assertNull($this->context->team());
        $this->assertArrayNotHasKey('team_id', $contexts[array_key_last($contexts)]);
    }

    public function test_it_restores_the_callers_tenant(): void
    {
        [$outer] = $this->tenantWithProject();
        [$inner] = $this->tenantWithProject();

        $this->context->set($outer, TenantContextOrigin::Path);

        (new ProbeTenantJob($inner->id))->handle();

        $this->assertSame($outer->id, $this->context->team()?->id);
        $this->assertSame(TenantContextOrigin::Path, $this->context->origin());
    }

    public function test_the_job_uses_the_tenant_cache_namespace(): void
    {
        [$team] = $this->tenantWithProject();

        (new ProbeTenantJob($team->id))->handle();

        $this->assertSame(['tenant:'.$team->id.':job.probe'], ProbeTenantJob::$cacheKeys);
        $this->assertTrue(Cache::get('tenant:'.$team->id.':job.probe'));
        $this->assertNull(Cache::get('job.probe'));
    }

    public function test_a_job_for_a_deleted_tenant_is_skipped_without_failing(): void
    {
        [$team] = $this->tenantWithProject();

        $team->delete();

        (new ProbeTenantJob($team->id))->handle();

        $this->assertSame([], ProbeTenantJob::$runs);
        $this->assertNull($this->context->team());
    }

    /**
     * @return array{0: Team, 1: Project}
     */
    private function tenantWithProject(): array
    {
        $user = User::factory()->create();
        $team = $this->createTeamFor($user);

        $project = $this->context->runFor($team, fn (): Project => Project::factory()->create([
            'team_id' => $team->id,
        ]));

        return [$team, $project];
    }
}

/**
 * Minimal job that records what it could see while it ran.
 *
 * Kept in the test file on purpose: it exists to prove the base class works, not
 * to be a production job.
 */
final class ProbeTenantJob extends TenantJob
{
    /** @var list<int> */
    public static array $runs = [];

    /** @var list<int> */
    public static array $projectIds = [];

    /** @var list<string> */
    public static array $cacheKeys = [];

    /**
     * {@inheritdoc}
     */
    protected function handleForTenant(Team $team): void
    {
        self::$runs[] = $team->id;
        self::$projectIds = Project::query()->pluck('id')->all();

        $cache = app(TenantCache::class);
        self::$cacheKeys[] = $cache->key('job.probe');
        $cache->put('job.probe', true);
    }
}
