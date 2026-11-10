<?php

namespace App\Contracts;

use App\Enums\Feature;
use App\Models\Team;
use App\Support\Entitlements;
use App\Support\NoSubscriptionSource;

/**
 * Where a tenant's purchased entitlements come from.
 *
 * The application never reads a plan name from a column: it asks
 * {@see Entitlements}, which asks this source. Today the only
 * implementation is {@see NoSubscriptionSource} (nobody subscribes
 * yet), and the SaaS billing workstream (B-2) will supply one that reads
 * `subscriptions` and `tenant_addons` instead — without touching a single
 * caller.
 *
 * Entitlements are the union of both answers: plan features ∪ active add-ons
 * (§B.4.2, PDR-06). Keeping them in one interface is what stops a caller from
 * re-implementing that union somewhere else.
 *
 * @see \docs\implementation\phase-04-plan-dan-entitlement.md tugas 4.3.1
 */
interface TeamEntitlementSource
{
    /**
     * The plan the tenant is subscribed to, or `null` when it has no subscription.
     *
     * Returning `null` is not an error: {@see Entitlements} then
     * falls back to `config('plans.default_plan')` (tugas 4.3.7).
     */
    public function planCodeFor(Team $team): ?string;

    /**
     * Features granted by add-ons that are currently active for the tenant.
     *
     * @return array<int, Feature>
     */
    public function addonFeaturesFor(Team $team): array;
}
