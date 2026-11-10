<?php

namespace App\Support;

use App\Contracts\TeamEntitlementSource;
use App\Enums\Feature;
use App\Models\Team;

/**
 * The entitlement source in use before SaaS billing exists.
 *
 * No tenant has a subscription or an add-on yet, so this answers "nothing
 * purchased" — which makes every tenant run on `config('plans.default_plan')`.
 * That is the honest answer, not a placeholder: inventing a plan here would make
 * tests and production disagree about who is entitled to what.
 *
 * Replaced (not extended) when the billing workstream lands, by rebinding
 * {@see TeamEntitlementSource} in the service container.
 */
final class NoSubscriptionSource implements TeamEntitlementSource
{
    public function planCodeFor(Team $team): ?string
    {
        return null;
    }

    /**
     * @return array<int, Feature>
     */
    public function addonFeaturesFor(Team $team): array
    {
        return [];
    }
}
