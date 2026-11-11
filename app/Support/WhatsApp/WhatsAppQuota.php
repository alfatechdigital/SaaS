<?php

namespace App\Support\WhatsApp;

use App\Enums\Feature;
use App\Models\Team;
use App\Models\UsageCounter;
use App\Support\Entitlements;
use App\Support\TenantContext;

/**
 * Per-tenant WhatsApp sending allowance for the current month.
 *
 * Two limits guard the same risk from different angles, and they are easy to
 * confuse:
 *
 * - **Quota** (here) is a monthly product limit from `config/plans.php`
 *   (`whatsapp_messages_per_month`). Hitting it means "the plan is used up".
 * - **Rate limit** ({@see WhatsAppSender}) is a per-minute transport guard.
 *   Hitting it means "slow down", not "you ran out".
 *
 * Usage is counted from outbound messages only. Inbound messages are the customer
 * speaking, and charging the tenant for those would make a busy support number
 * look like abuse.
 *
 * The counter row copies `limit` from the plan when the period first sees traffic
 * (design in phase-04 §4.5), so a mid-month plan change applies from the next
 * period and the enforced number stays readable.
 *
 * @see \docs\implementation\phase-05-fitur-masa-depan.md B-3.4
 */
final class WhatsAppQuota
{
    public const METRIC = 'whatsapp_outbound_messages';

    /**
     * Whether the tenant may send one more message this month.
     *
     * A plan without the metric (or a `0`) allows nothing, which is the intended
     * reading of "Basic does not include the WhatsApp bot".
     */
    public function hasRemaining(Team $team): bool
    {
        return $this->used($team) < $this->limit($team);
    }

    /**
     * Record one sent message.
     *
     * Called only after the provider accepted it: a failed send costs nothing and
     * must not eat the allowance.
     */
    public function record(Team $team): void
    {
        $counter = $this->counterFor($team);

        $counter->used++;
        $counter->save();
    }

    /**
     * Messages left this month. `0` means none.
     */
    public function remaining(Team $team): int
    {
        return max(0, $this->limit($team) - $this->used($team));
    }

    public function used(Team $team): int
    {
        return $this->counterFor($team)->used;
    }

    public function limit(Team $team): int
    {
        return $this->counterFor($team)->limit;
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
    private function counterFor(Team $team): UsageCounter
    {
        return app(TenantContext::class)->runFor($team, function () use ($team): UsageCounter {
            $counter = UsageCounter::query()
                ->where('metric', self::METRIC)
                ->where('period', $this->period())
                ->first();

            if ($counter !== null) {
                return $counter;
            }

            // Created through the relation so `team_id` is set without making it
            // mass-assignable.
            return $team->usageCounters()->create([
                'metric' => self::METRIC,
                'period' => $this->period(),
                'used' => 0,
                'limit' => $this->planLimit($team),
            ]);
        });
    }

    /**
     * The plan's monthly allowance for this metric.
     *
     * When the tenant only has the bot through an add-on (§B.4.2), the plan's
     * number is zero by definition — and zero would mean a paid feature that can
     * never send. Those tenants get `whatsapp.quota.addon_allowance` instead.
     */
    private function planLimit(Team $team): int
    {
        $planIncludesBot = in_array(
            Feature::WhatsAppBot->value,
            app(Entitlements::class)->plan($team)['features'],
            true,
        );

        if (! $planIncludesBot) {
            return max(0, (int) config('whatsapp.quota.addon_allowance', 500));
        }

        return max(0, $team->planLimit('whatsapp_messages_per_month') ?? 0);
    }
}
