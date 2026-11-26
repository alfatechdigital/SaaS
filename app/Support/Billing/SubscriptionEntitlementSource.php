<?php

namespace App\Support\Billing;

use App\Contracts\TeamEntitlementSource;
use App\Enums\Feature;
use App\Enums\SubscriptionStatus;
use App\Models\Team;
use App\Support\TenantContext;

/**
 * The real entitlement source: the tenant's running subscription decides its plan.
 *
 * "Running" is `ended_at IS NULL`; whether that subscription still grants access is
 * a separate question answered by {@see SubscriptionStatus::grantsAccess()}.
 * A tenant whose subscription has expired therefore falls back to
 * `config('plans.default_plan')` — it is no longer paying for anything. Cutting the
 * tenant off entirely is a different mechanism (`teams.suspended_at` + the retention
 * job), so the two causes never blur into one.
 *
 * The query runs inside the tenant's own context: `subscriptions` is tenant-owned and
 * the team global scope refuses to read it without a context, while callers may ask
 * this question from a console command or a test as well as from a request.
 */
final class SubscriptionEntitlementSource implements TeamEntitlementSource
{
    public function planCodeFor(Team $team): ?string
    {
        return app(TenantContext::class)->runFor($team, function (Team $team): ?string {
            $subscription = $team->currentSubscription;

            return $subscription !== null && $subscription->grantsAccess() === true
                ? $subscription->plan_code
                : null;
        });
    }

    /**
     * Features granted by add-ons.
     *
     * `tenant_addons` is built in tugas 25 Nov; until it exists a tenant cannot buy
     * an add-on, so the honest answer is "nothing purchased". Inventing a feature
     * here would hand out entitlements nobody paid for.
     *
     * @return array<int, Feature>
     */
    public function addonFeaturesFor(Team $team): array
    {
        return [];
    }
}
