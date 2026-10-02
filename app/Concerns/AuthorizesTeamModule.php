<?php

namespace App\Concerns;

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
}
