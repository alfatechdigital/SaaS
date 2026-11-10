<?php

namespace Tests\Support;

use App\Concerns\AuthorizesTeamModule;
use App\Enums\Feature;
use App\Models\Team;

/**
 * Exposes the trait's protected guard so its 403 behaviour can be asserted.
 */
final class FeatureGuardProbe
{
    use AuthorizesTeamModule;

    public function guard(Team $team, Feature $feature): void
    {
        $this->authorizeFeature($team, $feature);
    }
}
