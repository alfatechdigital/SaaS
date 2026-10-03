<?php

namespace App\Concerns;

use App\Models\Team;
use App\Scopes\TeamScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Applied to every model whose records are owned by a single team.
 *
 * @see \docs\IMPLEMENTATION_PLAN.md ADR-03
 * @see \docs\implementation\phase-02-tenant-context.md tugas 2.2.1 s/d 2.2.3
 */
trait BelongsToTeam
{
    /**
     * Register the team global scope.
     *
     * Fase 2 turns the scope on one model at a time (`usesTeamScope()`), so that a
     * failure is always attributable to a single model. Once every tenant model is
     * covered, that method's default becomes `true` and the per-model overrides go
     * away.
     */
    public static function bootBelongsToTeam(): void
    {
        if (static::usesTeamScope()) {
            static::addGlobalScope(new TeamScope);
        }
    }

    /**
     * Whether queries on this model are enforced by the {@see TeamScope}.
     *
     * @see \docs\implementation\phase-02-tenant-context.md tugas 2.2.1
     */
    protected static function usesTeamScope(): bool
    {
        return false;
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
     * Scope the query to a single team.
     *
     * Still worth writing even where the global scope already applies: it states
     * the intent at the call site and keeps working if the scope is lifted.
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
