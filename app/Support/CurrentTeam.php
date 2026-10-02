<?php

namespace App\Support;

use App\Models\Team;
use Illuminate\Http\Request;

/**
 * Resolves the `{current_team}` route parameter into a Team model.
 *
 * The `{current_team}` prefix is not registered as an implicit route binding, so
 * `$request->route('current_team')` normally yields the raw slug string.
 */
final class CurrentTeam
{
    /**
     * Resolve the team from the given request.
     */
    public static function from(Request $request): Team
    {
        $team = $request->route('current_team') ?? $request->route('team');

        if ($team instanceof Team) {
            return $team;
        }

        return Team::query()
            ->where('slug', (string) $team)
            ->firstOrFail();
    }
}
