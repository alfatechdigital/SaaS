<?php

namespace Tests\Unit;

use App\Enums\BillingInterval;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * The interval decides how long a paid period lasts, so its arithmetic has to be
 * calendar-correct — overcharging or undercharging a tenant by a day is a billing bug.
 */
class BillingIntervalTest extends TestCase
{
    public function test_a_monthly_period_does_not_overflow_into_the_next_month(): void
    {
        $end = BillingInterval::Monthly->advance(CarbonImmutable::parse('2026-01-31 10:00:00'));

        $this->assertSame('2026-02-28', $end->toDateString());
    }

    public function test_a_yearly_period_handles_a_leap_day(): void
    {
        $end = BillingInterval::Yearly->advance(CarbonImmutable::parse('2024-02-29 10:00:00'));

        $this->assertSame('2025-02-28', $end->toDateString());
    }

    public function test_a_yearly_period_lasts_twelve_months(): void
    {
        $start = CarbonImmutable::parse('2026-11-26 08:00:00');

        $this->assertTrue(
            $start->addMonthsNoOverflow(12)->equalTo(BillingInterval::Yearly->advance($start)),
        );
    }

    public function test_every_interval_has_a_distinct_label(): void
    {
        $labels = array_map(
            static fn (BillingInterval $interval): string => $interval->label(),
            BillingInterval::cases(),
        );

        $this->assertSame($labels, array_unique($labels));
    }
}
