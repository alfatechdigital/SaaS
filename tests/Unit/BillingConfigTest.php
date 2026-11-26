<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Guards `config/billing.php` against drift from §B.4.3 and from the plan list.
 */
class BillingConfigTest extends TestCase
{
    public function test_every_sellable_plan_has_a_price_entry_and_vice_versa(): void
    {
        $this->assertEqualsCanonicalizing(
            array_keys(config('plans.plans')),
            array_keys(config('billing.plans')),
        );
    }

    public function test_prices_match_the_decided_matrix(): void
    {
        $expected = [
            'basic' => [149000, 1490000],
            'plus' => [349000, 3490000],
            'pro' => [799000, 7990000],
        ];

        foreach ($expected as $code => [$monthly, $yearly]) {
            $this->assertSame($monthly, config("billing.plans.{$code}.price_monthly"), "Plan [{$code}] has the wrong monthly price.");
            $this->assertSame($yearly, config("billing.plans.{$code}.price_yearly"), "Plan [{$code}] has the wrong yearly price.");
        }
    }

    public function test_the_yearly_price_is_ten_months(): void
    {
        /** @var array<string, array{price_monthly: int, price_yearly: int|null}> $plans */
        $plans = config('billing.plans');

        foreach ($plans as $code => $plan) {
            $this->assertSame(
                $plan['price_monthly'] * 10,
                $plan['price_yearly'],
                "Plan [{$code}] must be ten monthly payments — two months free (PDR-05).",
            );
        }
    }

    public function test_lifecycle_durations_match_b_4_3(): void
    {
        $this->assertSame(30, config('billing.trial_days'));
        $this->assertSame(7, config('billing.grace_days'));
        $this->assertSame(30, config('billing.retention_days'));
    }

    public function test_dunning_sends_three_reminders_inside_the_grace_period(): void
    {
        /** @var array<int, int> $days */
        $days = config('billing.dunning.days');

        $this->assertCount(3, $days, '§B.4.3 promises three reminders.');

        $sorted = $days;
        sort($sorted);
        $this->assertSame($sorted, $days, 'Reminders must be listed in the order they are sent.');
        $this->assertSame([0, 3, 6], $days);
        $this->assertLessThan(
            (int) config('billing.grace_days'),
            max($days),
            'The last reminder must land before the grace period expires.',
        );
    }
}
