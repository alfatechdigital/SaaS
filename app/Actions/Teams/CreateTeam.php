<?php

namespace App\Actions\Teams;

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateTeam
{
    /**
     * Create a new team and add the user as owner.
     *
     * @param  bool  $switchToTeam  Whether the given user should also be switched
     *                              to the new team as their active team. The
     *                              platform panel passes `false`: creating a
     *                              tenant for someone else must not move the
     *                              operator out of their own team.
     */
    public function handle(User $user, string $name, bool $isPersonal = false, bool $switchToTeam = true): Team
    {
        return DB::transaction(function () use ($user, $name, $isPersonal, $switchToTeam) {
            $team = Team::create([
                'name' => $name,
                'is_personal' => $isPersonal,
            ]);

            $team->memberships()->create([
                'user_id' => $user->id,
                'role' => TeamRole::Owner,
            ]);

            if ($switchToTeam) {
                $user->switchTeam($team);
            }

            return $team;
        });
    }
}
