<?php

namespace Tests\Feature\Tenancy;

use App\Enums\TenantContextOrigin;
use App\Models\Team;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Contracts\Queue\Job as QueueJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Tugas 2.6.3 — `team_id` in the log context.
 *
 * Read back through the `MessageLogged` event rather than by inspecting the log
 * file: the event carries the merged context that actually reaches the handler,
 * which is the only thing an operator would see.
 *
 * @see \docs\implementation\phase-02-tenant-context.md tugas 2.6.3
 */
class TenantLogContextTest extends TestCase
{
    use RefreshDatabase;

    private TenantContext $context;

    /** @var list<array<string, mixed>> */
    private array $contexts = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = app(TenantContext::class);

        Log::listen(function (MessageLogged $event): void {
            $this->contexts[] = $event->context;
        });
    }

    public function test_a_log_line_carries_the_active_tenant(): void
    {
        $team = Team::factory()->create();

        $this->context->set($team, TenantContextOrigin::Console);

        $this->assertSame($team->id, $this->loggedTeamId());
    }

    public function test_switching_tenant_replaces_the_logged_tenant(): void
    {
        $first = Team::factory()->create();
        $second = Team::factory()->create();

        $this->context->set($first, TenantContextOrigin::Console);
        $this->context->set($second, TenantContextOrigin::Console);

        $this->assertSame($second->id, $this->loggedTeamId());
    }

    public function test_forgetting_the_tenant_removes_it_from_the_log_context(): void
    {
        $this->context->set(Team::factory()->create(), TenantContextOrigin::Console);

        $this->context->forget();

        $this->assertArrayNotHasKey('team_id', $this->loggedContext());
    }

    public function test_run_for_restores_the_outer_tenant_in_the_log_context(): void
    {
        $outer = Team::factory()->create();
        $inner = Team::factory()->create();

        $this->context->set($outer, TenantContextOrigin::Console);

        $this->context->runFor($inner, function () use ($inner): void {
            $this->assertSame($inner->id, $this->loggedTeamId());
        });

        $this->assertSame($outer->id, $this->loggedTeamId());
    }

    public function test_a_request_logs_the_tenant_it_serves(): void
    {
        $user = User::factory()->create();
        $team = $this->createTeamFor($user);

        $this->actingAs($user)
            ->get(route('projects.index', ['current_team' => $team->slug]))
            ->assertOk();

        $this->assertSame($team->id, $this->loggedTeamId());
    }

    /**
     * The leak this guards against: a worker runs many jobs in one process, so a
     * `team_id` left over from the previous job would mislabel every line of the
     * next one.
     */
    public function test_a_starting_queued_job_clears_the_tenant_from_the_log_context(): void
    {
        $this->context->set(Team::factory()->create(), TenantContextOrigin::Path);

        Event::dispatch(new JobProcessing('database', $this->createMock(QueueJob::class)));

        $this->assertArrayNotHasKey('team_id', $this->loggedContext());
    }

    /**
     * @return array<string, mixed>
     */
    private function loggedContext(): array
    {
        $this->contexts = [];

        Log::info('tenant log context probe');

        $this->assertNotEmpty($this->contexts, 'No log line was recorded.');

        return $this->contexts[array_key_last($this->contexts)];
    }

    private function loggedTeamId(): int
    {
        $context = $this->loggedContext();

        $this->assertArrayHasKey('team_id', $context);

        return (int) $context['team_id'];
    }
}
