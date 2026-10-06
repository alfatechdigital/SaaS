<?php

namespace App\Scopes;

use App\Concerns\BelongsToTeam;
use App\Exceptions\MissingTenantContext;
use App\Support\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Restricts every query on a tenant-owned model to the active tenant.
 *
 * This is the difference between "isolation by discipline" and "isolation by
 * architecture": before it existed, forgetting `->forTeam()` in one controller
 * silently leaked another tenant's rows. Now the filter is part of the query
 * itself, and a query with no active tenant fails instead of guessing.
 *
 * Two escape hatches exist, both deliberately explicit:
 * - `Model::withoutTeamScope()` (see {@see BelongsToTeam}) for
 *   cross-tenant work such as the platform layer, seeders and commands;
 * - `TenantContext::runFor()` to run work for one tenant from a context that has
 *   none (queued job, seeder, command).
 *
 * @see BelongsToTeam
 * @see \docs\implementation\phase-02-tenant-context.md tugas 2.2.1 s/d 2.2.7
 *
 * @implements Scope<Model>
 */
class TeamScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $team = app(TenantContext::class)->team();

        if ($team === null) {
            throw MissingTenantContext::forModel($model::class);
        }

        $builder->where($model->getTable().'.team_id', $team->id);
    }
}
