<?php

namespace App\Support;

use App\Enums\TenantContextOrigin;
use App\Http\Middleware\ResetTenantContext;
use App\Models\Team;
use Closure;

/**
 * The single source of truth for "which tenant is this work running for".
 *
 * Before this class existed, every controller resolved the tenant on its own via
 * {@see CurrentTeam::from()}, and nothing carried that answer forward. That is
 * fine while isolation is enforced by hand, but the global scope planned for Fase
 * 2 needs one place to ask, otherwise storage, cache, queue and logging cannot
 * follow the tenant consistently.
 *
 * Registered as a singleton, so the answer is per request — and, in a queue
 * worker, per job after {@see ResetTenantContext} (request)
 * or the `JobProcessing` listener in `AppServiceProvider` (job) has cleared it.
 *
 * @see TenantContextOrigin
 * @see \docs\implementation\phase-02-tenant-context.md tugas 2.1.1 s/d 2.1.4
 */
final class TenantContext
{
    /**
     * The tenant the current request or job runs for.
     */
    private ?Team $team = null;

    /**
     * How that tenant was resolved. `null` while no tenant is active.
     */
    private ?TenantContextOrigin $origin = null;

    /**
     * Mark a tenant as active.
     *
     * Called by {@see CurrentTeam::from()} for inbound requests, and explicitly by
     * console/job code that steps outside a single tenant.
     */
    public function set(Team $team, TenantContextOrigin $origin = TenantContextOrigin::Path): void
    {
        $this->team = $team;
        $this->origin = $origin;
    }

    /**
     * The active tenant, or `null` when none is active.
     */
    public function team(): ?Team
    {
        return $this->team;
    }

    /**
     * Whether a tenant is currently active.
     */
    public function isResolved(): bool
    {
        return $this->team !== null;
    }

    /**
     * How the active tenant was resolved, or `null` when none is active.
     */
    public function origin(): ?TenantContextOrigin
    {
        return $this->origin;
    }

    /**
     * Drop the active tenant.
     *
     * Must run before every request and every queued job, otherwise a long-lived
     * process (queue worker, Octane) would carry the previous tenant into work
     * that never asked for it.
     */
    public function forget(): void
    {
        $this->team = null;
        $this->origin = null;
    }

    /**
     * Run a callback with an explicit tenant, then restore the previous context.
     *
     * This is the only supported way for jobs, commands and seeders to act on a
     * tenant. Restoring (rather than merely forgetting) keeps nesting safe: an
     * inner `runFor()` cannot silently widen the outer one.
     *
     * @param  Closure(Team): mixed  $callback
     */
    public function runFor(
        Team $team,
        Closure $callback,
        TenantContextOrigin $origin = TenantContextOrigin::Console,
    ): mixed {
        $previousTeam = $this->team;
        $previousOrigin = $this->origin;

        $this->set($team, $origin);

        try {
            return $callback($team);
        } finally {
            $this->team = $previousTeam;
            $this->origin = $previousOrigin;
        }
    }
}
