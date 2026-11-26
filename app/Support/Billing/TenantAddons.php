<?php

namespace App\Support\Billing;

use App\Enums\Feature;
use App\Models\Team;
use App\Models\TenantAddon;
use App\Support\Entitlements;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;

/**
 * Granting and revoking add-ons (PDR-06, B-2 tugas 26 Nov).
 *
 * The only writer of `tenant_addons`, for the same reason {@see SubscriptionService}
 * owns `subscriptions`: "is this feature paid for?" must have one answer, and the
 * platform panel is not the only future caller (self-service purchase is gelombang 2).
 *
 * An add-on is time-boxed, not deleted: revoking sets `ends_at` to now, so the audit
 * trail and the entitlement answer stay consistent — an ended row simply stops
 * counting. Granting again extends the existing row, because the pair
 * `(team_id, feature)` is unique and two overlapping rows for one feature would be
 * impossible to reason about.
 */
final class TenantAddons
{
    /**
     * The features this tenant currently has through add-ons.
     *
     * Unknown stored values (a feature removed from the enum) are skipped rather
     * than fatal: entitlement must not break because a row outlived its feature.
     *
     * @return array<int, Feature>
     */
    public function active(Team $team): array
    {
        return app(TenantContext::class)->runFor($team, function (): array {
            return TenantAddon::query()
                ->active()
                ->orderBy('feature')
                ->pluck('feature')
                ->map(fn (string $value): ?Feature => Feature::tryFrom($value))
                ->filter()
                ->values()
                ->all();
        });
    }

    /**
     * Every add-on row of the tenant, active or not, newest first.
     *
     * Used by the platform panel: an operator needs to see what a tenant bought,
     * including the ones that already ran out.
     *
     * @return array<int, TenantAddon>
     */
    public function all(Team $team): array
    {
        return app(TenantContext::class)->runFor($team, function (): array {
            return TenantAddon::query()->orderByDesc('starts_at')->get()->all();
        });
    }

    /**
     * Make the feature available to the tenant, until `$until` when given.
     */
    public function grant(Team $team, Feature $feature, ?CarbonImmutable $until = null, ?string $providerReference = null): TenantAddon
    {
        return app(TenantContext::class)->runFor($team, function () use ($team, $feature, $until, $providerReference): TenantAddon {
            $now = CarbonImmutable::now();

            $addon = TenantAddon::query()
                ->where('feature', $feature->value)
                ->first();

            if ($addon === null) {
                // Created through the relation so `team_id` is set without making
                // it mass-assignable.
                return $team->tenantAddons()->create([
                    'feature' => $feature->value,
                    'starts_at' => $now,
                    'ends_at' => $until,
                    'provider_reference' => $providerReference,
                ]);
            }

            // A lapsed add-on starts over today; a live one keeps its original
            // start date so the panel can still show how long it has been held.
            $addon->fill([
                'starts_at' => $addon->isActive() ? $addon->starts_at : $now,
                'ends_at' => $until,
                'provider_reference' => $providerReference ?? $addon->provider_reference,
            ])->save();

            return $addon;
        });
    }

    /**
     * End the add-on now. A tenant that never had it is a no-op.
     */
    public function revoke(Team $team, Feature $feature): void
    {
        app(TenantContext::class)->runFor($team, function () use ($feature): void {
            $addon = TenantAddon::query()
                ->where('feature', $feature->value)
                ->first();

            if ($addon === null || ! $addon->isActive()) {
                return;
            }

            $addon->update(['ends_at' => CarbonImmutable::now()]);
        });
    }

    /**
     * Whether the plan can already use the feature without an add-on.
     *
     * Kept here so the panel does not have to re-derive the rule: selling an add-on
     * for a feature the plan includes would charge twice for the same thing.
     */
    public function coveredByPlan(Team $team, Feature $feature): bool
    {
        return in_array($feature->value, app(Entitlements::class)->plan($team)['features'], true);
    }
}
