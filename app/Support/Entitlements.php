<?php

namespace App\Support;

use App\Contracts\TeamEntitlementSource;
use App\Enums\Feature;
use App\Exceptions\UnknownPlan;
use App\Models\Team;
use LogicException;

/**
 * Answers "may this tenant use feature X?" — the only supported way to ask.
 *
 * Two things make this worth a class instead of a helper on the model:
 *
 * 1. **One resolution order.** Effective features are the tenant's plan features
 *    ∪ its active add-ons (§B.4.2). Spreading that union over controllers would
 *    produce callsites that forget the add-ons, so it lives here once.
 * 2. **No plan-name branching.** Callers ask about a {@see Feature}, never about
 *    a plan. There is deliberately no `isPro()`: an `if ($team->plan === 'pro')`
 *    scattered through the codebase cannot be changed when the product decides
 *    to move a feature between plans (tugas 4.3.5).
 *
 * Plan contents themselves are data in `config/plans.php` (ADR-14).
 *
 * @see \docs\implementation\phase-04-plan-dan-entitlement.md tugas 4.3.1 s/d 4.3.7
 */
final class Entitlements
{
    public function __construct(private readonly TeamEntitlementSource $source) {}

    /**
     * The plan code in force for the tenant.
     *
     * Falls back to `config('plans.default_plan')` when the tenant has no
     * subscription, which is every tenant until billing lands (tugas 4.3.7).
     *
     * @throws UnknownPlan when the resolved plan is missing from config
     */
    public function planCode(Team $team): string
    {
        $planCode = $this->source->planCodeFor($team) ?? $this->defaultPlanCode();

        if (! array_key_exists($planCode, $this->plans())) {
            throw UnknownPlan::named($planCode);
        }

        return $planCode;
    }

    /**
     * The plan definition (name, features, limits) in force for the tenant.
     *
     * @return array{name: string, features: array<int, string>, limits: array<string, int>}
     *
     * @throws UnknownPlan when the resolved plan is missing from config
     */
    public function plan(Team $team): array
    {
        return $this->plans()[$this->planCode($team)];
    }

    /**
     * Every feature the tenant may use, as enum values, in config order.
     *
     * Plan features first, then add-ons — both deduplicated, so the answer is a
     * set that the frontend can consume directly (tugas 4.3.3).
     *
     * @return array<int, string>
     */
    public function features(Team $team): array
    {
        $addonFeatures = array_map(
            fn (Feature $feature): string => $feature->value,
            $this->source->addonFeaturesFor($team),
        );

        return array_values(array_unique([...$this->plan($team)['features'], ...$addonFeatures]));
    }

    /**
     * Whether the tenant may use the given feature (tugas 4.3.2).
     */
    public function can(Team $team, Feature $feature): bool
    {
        return in_array($feature->value, $this->features($team), true);
    }

    /**
     * A numeric quota of the tenant's plan, or `null` when the metric is unknown.
     *
     * `0` is a real answer and means "not allowed"; only a missing metric yields
     * `null`. Enforcement is not part of this phase — the billing workstream adds
     * `usage_counters` and the checks that consume these numbers.
     */
    public function limit(Team $team, string $metric): ?int
    {
        $limit = $this->plan($team)['limits'][$metric] ?? null;

        return is_int($limit) ? $limit : null;
    }

    /**
     * The plan used for tenants without a subscription.
     *
     * @throws LogicException when config carries no usable default
     */
    private function defaultPlanCode(): string
    {
        $default = config('plans.default_plan');

        if (! is_string($default) || $default === '') {
            throw new LogicException('config/plans.php must define a non-empty `default_plan`.');
        }

        return $default;
    }

    /**
     * All plan definitions, keyed by plan code.
     *
     * @return array<string, array{name: string, features: array<int, string>, limits: array<string, int>}>
     */
    private function plans(): array
    {
        /** @var array<string, array{name: string, features: array<int, string>, limits: array<string, int>}> $plans */
        $plans = config('plans.plans');

        return $plans;
    }
}
