<?php

namespace App\Support\Billing;

use App\Models\Team;
use App\Models\UsageCounter;
use App\Support\Entitlements;
use App\Support\TenantContext;

/**
 * A monthly plan allowance that is consumed per use (B-2, tugas 25 Nov).
 *
 * Distinct from {@see SeatQuota}: seats are counted (a person exists or not),
 * usage is incremented (each generation is gone once spent). Both end up in the
 * same `usage_counters` shape when a number is needed, but only the incremental
 * one is stored.
 *
 * The counter row copies `limit` from the plan when the period first sees
 * traffic — phase-04 §4.5.5 — so raising a plan mid-month applies from the next
 * period and the number that was enforced stays readable afterwards.
 *
 * `ai_generations_per_month` is 0 on Basic and Plus, which is the intended
 * reading of "Content AI is a Pro feature": there is no allowance to spend.
 * The first consumer will be the Content AI module (Fase 5, B-7); the meter is
 * built now because the quota is part of the plan contract, not of that module.
 */
final class MonthlyQuota
{
    public const AI_GENERATIONS = 'ai_generations';

    /**
     * Whether the tenant may spend one more unit this month.
     */
    public function hasRemaining(Team $team, string $metric): bool
    {
        return $this->used($team, $metric) < $this->limit($team, $metric);
    }

    /**
     * Record consumption.
     *
     * Called only after the work was produced: a failed request costs nothing and
     * must not eat the allowance.
     */
    public function record(Team $team, string $metric, int $amount = 1): void
    {
        // Atomic increment: two workers spending for the same tenant must not
        // read-modify-write the same row and lose a unit from the count.
        $this->counterFor($team, $metric)->increment('used', $amount);
    }

    public function used(Team $team, string $metric): int
    {
        return $this->counterFor($team, $metric)->used;
    }

    public function limit(Team $team, string $metric): int
    {
        return $this->counterFor($team, $metric)->limit;
    }

    public function remaining(Team $team, string $metric): int
    {
        return max(0, $this->limit($team, $metric) - $this->used($team, $metric));
    }

    /**
     * The current period as `YYYY-MM`, the shape the counter table stores.
     */
    public function period(): string
    {
        return now()->format('Y-m');
    }

    /**
     * Fetch — creating on first use — this period's counter for the tenant.
     *
     * Wrapped in {@see TenantContext::runFor()} because the counter is a
     * tenant-scoped model and background work has no tenant of its own.
     */
    private function counterFor(Team $team, string $metric): UsageCounter
    {
        return app(TenantContext::class)->runFor($team, function () use ($team, $metric): UsageCounter {
            $counter = UsageCounter::query()
                ->where('metric', $metric)
                ->where('period', $this->period())
                ->first();

            if ($counter !== null) {
                return $counter;
            }

            // Created through the relation so `team_id` is set without making it
            // mass-assignable.
            return $team->usageCounters()->create([
                'metric' => $metric,
                'period' => $this->period(),
                'used' => 0,
                'limit' => $this->planLimit($team, $metric),
            ]);
        });
    }

    /**
     * The plan's allowance for this metric.
     *
     * The counter metric and the config key are not always spelled the same way:
     * the table stores what is counted (`ai_generations`), while the plan stores
     * how often per month.
     */
    private function planLimit(Team $team, string $metric): int
    {
        $limitKey = match ($metric) {
            self::AI_GENERATIONS => 'ai_generations_per_month',
            default => $metric,
        };

        return max(0, app(Entitlements::class)->limit($team, $limitKey) ?? 0);
    }
}
