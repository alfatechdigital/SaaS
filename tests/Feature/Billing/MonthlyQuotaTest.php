<?php

namespace Tests\Feature\Billing;

use App\Models\Team;
use App\Models\UsageCounter;
use App\Support\Billing\MonthlyQuota;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The monthly usage meter behind `ai_generations_per_month` (B-2, tugas 25 Nov):
 * 50 on Pro, 0 on Basic/Plus, reset every period, and the enforced number stays
 * readable after a plan change.
 */
class MonthlyQuotaTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_allowance_comes_from_the_plan(): void
    {
        $quota = app(MonthlyQuota::class);

        // A fresh team per plan: the counter row is created once per period and
        // keeps the number it was created with.
        $this->usePlan('pro');
        $pro = Team::factory()->create();
        $this->assertSame(50, $quota->limit($pro, MonthlyQuota::AI_GENERATIONS));
        $this->assertTrue($quota->hasRemaining($pro, MonthlyQuota::AI_GENERATIONS));

        $this->usePlan('plus');
        $plus = Team::factory()->create();
        $this->assertSame(0, $quota->limit($plus, MonthlyQuota::AI_GENERATIONS));
        $this->assertFalse($quota->hasRemaining($plus, MonthlyQuota::AI_GENERATIONS));

        $this->usePlan('basic');
        $basic = Team::factory()->create();
        $this->assertSame(0, $quota->limit($basic, MonthlyQuota::AI_GENERATIONS));
    }

    public function test_usage_is_counted_until_the_allowance_runs_out(): void
    {
        $this->usePlan('pro');

        $team = Team::factory()->create();
        $quota = app(MonthlyQuota::class);

        $quota->record($team, MonthlyQuota::AI_GENERATIONS, 49);

        $this->assertSame(49, $quota->used($team, MonthlyQuota::AI_GENERATIONS));
        $this->assertSame(1, $quota->remaining($team, MonthlyQuota::AI_GENERATIONS));
        $this->assertTrue($quota->hasRemaining($team, MonthlyQuota::AI_GENERATIONS));

        $quota->record($team, MonthlyQuota::AI_GENERATIONS);

        $this->assertSame(0, $quota->remaining($team, MonthlyQuota::AI_GENERATIONS));
        $this->assertFalse($quota->hasRemaining($team, MonthlyQuota::AI_GENERATIONS));
    }

    public function test_one_counter_row_serves_the_whole_period(): void
    {
        $this->usePlan('pro');

        $team = Team::factory()->create();
        $quota = app(MonthlyQuota::class);

        $quota->record($team, MonthlyQuota::AI_GENERATIONS);
        $quota->record($team, MonthlyQuota::AI_GENERATIONS);

        $rows = app(TenantContext::class)->runFor(
            $team,
            fn () => UsageCounter::query()->where('metric', MonthlyQuota::AI_GENERATIONS)->get(),
        );

        $this->assertCount(1, $rows);
        $this->assertSame(2, $rows->first()?->used);
        $this->assertSame(now()->format('Y-m'), $rows->first()?->period);
    }

    public function test_the_enforced_limit_is_frozen_when_the_period_starts(): void
    {
        $this->usePlan('plus');

        $team = Team::factory()->create();
        $quota = app(MonthlyQuota::class);

        // Plus has no AI allowance, so this period's row records 0…
        $quota->record($team, MonthlyQuota::AI_GENERATIONS);

        // …and an upgrade mid-period does not silently rewrite what was enforced.
        $this->usePlan('pro');

        $this->assertSame(0, $quota->limit($team, MonthlyQuota::AI_GENERATIONS));
    }

    public function test_a_new_period_gets_a_fresh_allowance(): void
    {
        $this->usePlan('pro');

        $team = Team::factory()->create();
        $quota = app(MonthlyQuota::class);

        $this->travelTo(now()->startOfMonth()->addDay());
        $quota->record($team, MonthlyQuota::AI_GENERATIONS, 50);
        $this->assertFalse($quota->hasRemaining($team, MonthlyQuota::AI_GENERATIONS));

        $this->travelTo(now()->startOfMonth()->addMonth()->addDay());

        $this->assertSame(0, $quota->used($team, MonthlyQuota::AI_GENERATIONS));
        $this->assertSame(50, $quota->remaining($team, MonthlyQuota::AI_GENERATIONS));
    }
}
