<?php

namespace App\Concerns;

use App\Enums\Feature;
use App\Enums\TeamPermission;
use App\Models\Team;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Guards read endpoints (`index`) that have no FormRequest to authorise them.
 */
trait AuthorizesTeamModule
{
    /**
     * Abort unless the user belongs to the team and holds the given permission.
     *
     * @throws HttpException
     */
    protected function authorizeModule(?User $user, Team $team, TeamPermission $permission): void
    {
        abort_if($user === null || ! $user->belongsToTeam($team), 403);

        abort_unless($user->hasTeamPermission($team, $permission), 403);
    }

    /**
     * Abort unless the tenant's plan (plus add-ons) includes the given feature.
     *
     * Entitlement belongs in the controller or policy, not only in the UI: hiding
     * a button protects nothing (Fase 4, tugas 4.3.4 dan 4.6.3).
     *
     * @throws HttpException
     */
    protected function authorizeFeature(Team $team, Feature $feature): void
    {
        abort_unless($team->can($feature), 403);
    }
}
