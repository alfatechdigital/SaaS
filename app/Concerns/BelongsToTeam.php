<?php

namespace App\Concerns;

use App\Models\Team;
use App\Scopes\TeamScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Applied to every model whose records are owned by a single team.
 *
 * The global scope registered here is the *only* thing that enforces tenant
 * isolation on reads; `->forTeam()` is a narrowing filter, not a safeguard, and
 * the controllers therefore no longer rely on it (tugas 2.2.6).
 *
 * @see \docs\IMPLEMENTATION_PLAN.md ADR-03
 * @see \docs\implementation\phase-02-tenant-context.md tugas 2.2.1 s/d 2.2.7
 */
trait BelongsToTeam
{
    /**
     * Register the team global scope.
     *
     * Every model that uses this trait is tenant-owned, so the scope is
     * unconditional: querying without an active tenant fails loudly instead of
     * returning another tenant's rows. `withoutTeamScope()` is the only way out.
     *
     * @see TeamScope
     */
    public static function bootBelongsToTeam(): void
    {
        static::addGlobalScope(new TeamScope);
    }

    /**
     * Get the team that owns the model.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Narrow the query to a single team.
     *
     * The global scope is the authority, so this can never widen a query: asking
     * for a team other than the active one returns no rows rather than that
     * team's rows (pinned down by `TeamGlobalScopeTest`). Since the scope already
     * confines every query to the active tenant, the tenant-owned controllers no
     * longer pass `->forTeam()` — see tugas 2.2.6 — and this filter is left for
     * call sites and tests that need to name a team explicitly.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeForTeam(Builder $query, Team|int $team): Builder
    {
        return $query->where(
            $this->getTable().'.team_id',
            $team instanceof Team ? $team->id : $team,
        );
    }

    /**
     * Escape hatch: run the query across every tenant.
     *
     * Deliberately explicit so that every use is easy to spot in review. Only
     * reach for it when the code genuinely works outside a single tenant — the
     * platform layer, a seeder, a maintenance command — and say why in a comment.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeWithoutTeamScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope(TeamScope::class);
    }
}
