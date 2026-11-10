<?php

namespace Tests\Support;

use App\Contracts\TeamEntitlementSource;
use App\Enums\Feature;
use App\Models\Team;

/**
 * Stands in for the billing-driven source so entitlement rules can be exercised
 * before `subscriptions` and `tenant_addons` exist.
 */
final class FakeTeamEntitlementSource implements TeamEntitlementSource
{
    /**
     * @param  array<int, Feature>  $addonFeatures
     */
    public function __construct(
        public ?string $planCode = null,
        public array $addonFeatures = [],
    ) {}

    public function planCodeFor(Team $team): ?string
    {
        return $this->planCode;
    }

    /**
     * @return array<int, Feature>
     */
    public function addonFeaturesFor(Team $team): array
    {
        return $this->addonFeatures;
    }
}
