<?php

namespace App\Support;

use App\Models\Team;
use App\Models\User;

/**
 * Builds the member option list used by assignee / PIC pickers.
 */
final class TeamMembers
{
    /**
     * Get the members of the given team as a plain option list.
     *
     * @return array<int, array{id: int, name: string, jobTitle: string|null, phone: string|null}>
     */
    public static function options(Team $team): array
    {
        return $team->members()
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'jobTitle' => $user->job_title,
                'phone' => $user->phone,
            ])
            ->all();
    }
}
