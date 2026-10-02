<?php

namespace App\Concerns;

use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Applied to every model whose records are owned by a single team.
 *
 * @see \docs\IMPLEMENTATION_PLAN.md ADR-03
 */
trait BelongsToTeam
{
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
}
