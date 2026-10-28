<?php

namespace App\Jobs;

use App\Models\Team;
use App\Providers\AppServiceProvider;
use App\Support\TenantCache;
use App\Support\TenantContext;
use App\Support\TenantStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Base class for every job that works for one tenant.
 *
 * A queue worker boots the application once and runs many jobs, and
 * {@see AppServiceProvider::configureTenantContext()} clears the
 * active tenant before each one. So a job cannot rely on the tenant that
 * happened to be active when it was dispatched — it has to carry the id in its
 * payload and re-establish the context itself.
 *
 * `handle()` is `final` on purpose: subclasses cannot forget the wrapper, and
 * therefore cannot query a tenant-scoped model without a tenant (which would
 * throw `MissingTenantContext` inside the worker, far from the code that caused
 * it).
 *
 * @see \docs\implementation\phase-02-tenant-context.md tugas 2.6.2
 */
abstract class TenantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  int  $teamId  The tenant this job works for, carried in the payload.
     *
     * Not `readonly`: {@see SerializesModels} restores job properties by
     * reflection after unserialize, which a readonly property refuses.
     */
    public function __construct(public int $teamId) {}

    /**
     * Run the job inside its own tenant context.
     *
     * A tenant deleted (or soft-deleted) between dispatch and execution is not an
     * error: the work the job was created for no longer exists. Such a job logs
     * the fact and exits instead of failing, retrying, and filling the queue with
     * work that can never succeed.
     */
    final public function handle(): void
    {
        $team = Team::query()->find($this->teamId);

        if ($team === null) {
            Log::warning('Skipping a tenant job because its tenant no longer exists.', [
                'job' => static::class,
                'team_id' => $this->teamId,
            ]);

            return;
        }

        app(TenantContext::class)->runFor($team, function (Team $team): void {
            $this->handleForTenant($team);
        });
    }

    /**
     * Do the work with the job's tenant active.
     *
     * Tenant-scoped queries, {@see TenantCache} and
     * {@see TenantStorage} all work inside this method and refuse to
     * work outside it.
     */
    abstract protected function handleForTenant(Team $team): void;
}
