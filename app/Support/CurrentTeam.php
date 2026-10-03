<?php

namespace App\Support;

use App\Enums\TenantContextOrigin;
use App\Models\Team;
use Illuminate\Http\Request;

/**
 * Resolves the `{current_team}` route parameter into a Team model.
 *
 * The `{current_team}` prefix is not registered as an implicit route binding, so
 * `$request->route('current_team')` normally yields the raw slug string.
 *
 * Resolving a team also *publishes* it as the active tenant, so the rest of the
 * request (storage, cache, logging, and the Fase 2 global scope) has one place to
 * ask. The resolution itself is unchanged, so callers see no difference.
 *
 * @see TenantContext
 * @see \docs\implementation\phase-02-tenant-context.md tugas 2.1.2
 */
final class CurrentTeam
{
    /**
     * Resolve the team from the given request and make it the active tenant.
     */
    public static function from(Request $request): Team
    {
        $team = $request->route('current_team') ?? $request->route('team');

        if (! $team instanceof Team) {
            $team = Team::query()
                ->where('slug', (string) $team)
                ->firstOrFail();
        }

        app(TenantContext::class)->set($team, TenantContextOrigin::Path);

        return $team;
    }
}
