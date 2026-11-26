<?php

namespace Tests\Feature\Billing;

use App\Enums\BillingInterval;
use App\Enums\SubscriptionStatus;
use App\Exceptions\InvalidSubscriptionTransition;
use App\Exceptions\SubscriptionAlreadyRunning;
use App\Exceptions\UnknownPlan;
use App\Models\Subscription;
use App\Models\Team;
use App\Support\Billing\SubscriptionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The subscription service is the only writer of `subscriptions`, so every rule of
 * §B.4.3 that touches the lifecycle is pinned down here.
 */
class SubscriptionServiceTest extends TestCase
{
    use RefreshDatabase;

    private SubscriptionService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(SubscriptionService::class);
    }

    public function test_a_trial_starts_on_the_default_plan_for_the_configured_days(): void
    {
        $team = Team::factory()->create();
        $start = CarbonImmutable::parse('2026-12-01 08:00:00');

        $subscription = $this->service->startTrial($team, startedAt: $start);

        $this->assertSame(config('plans.default_plan'), $subscription->plan_code);
        $this->assertSame(SubscriptionStatus::Trialing, $subscription->status);
        $this->assertNull($subscription->billing_interval);
        $this->assertSame((string) config('billing.provider'), $subscription->provider);
        $this->assertTrue($subscription->isCurrent());
        $this->assertTrue($start->addDays((int) config('billing.trial_days'))->equalTo($subscription->trial_ends_at));
        $this->assertTrue($start->equalTo($subscription->current_period_start));
        $this->assertTrue($subscription->trial_ends_at->equalTo($subscription->current_period_end));
    }

    public function test_a_trial_can_start_on_another_plan(): void
    {
        $subscription = $this->service->startTrial(Team::factory()->create(), 'plus');

        $this->assertSame('plus', $subscription->plan_code);
    }

    public function test_a_plan_missing_from_config_is_refused(): void
    {
        $this->expectException(UnknownPlan::class);

        $this->service->startTrial(Team::factory()->create(), 'enterprise');
    }

    public function test_a_second_running_subscription_is_refused(): void
    {
        $team = Team::factory()->create();

        $this->service->startTrial($team);

        $this->expectException(SubscriptionAlreadyRunning::class);

        $this->service->startTrial($team);
    }

    public function test_paying_a_trial_starts_the_paid_period_when_the_trial_ends(): void
    {
        $start = CarbonImmutable::parse('2026-12-01 08:00:00');
        $trial = $this->service->startTrial(Team::factory()->create(), startedAt: $start);
        $trialEnd = $start->addDays((int) config('billing.trial_days'));

        // The tenant pays ten days into the trial; the trial days are not lost.
        $activated = $this->service->activate(
            $trial,
            BillingInterval::Monthly,
            'midtrans-order-1',
            $start->addDays(10),
        );

        $this->assertSame(SubscriptionStatus::Active, $activated->status);
        $this->assertSame(BillingInterval::Monthly, $activated->billing_interval);
        $this->assertSame('midtrans-order-1', $activated->provider_reference);
        $this->assertTrue($trialEnd->equalTo($activated->current_period_start));
        $this->assertTrue($trialEnd->addMonthNoOverflow()->equalTo($activated->current_period_end));
    }

    public function test_a_yearly_payment_buys_twelve_months(): void
    {
        $trial = $this->service->startTrial(Team::factory()->create());
        $trialEnd = $trial->current_period_end;

        $activated = $this->service->activate($trial, BillingInterval::Yearly);

        $this->assertSame(BillingInterval::Yearly, $activated->billing_interval);
        $this->assertTrue($trialEnd->addYearNoOverflow()->equalTo($activated->current_period_end));
    }

    public function test_paying_after_the_period_ended_starts_a_new_period_at_payment(): void
    {
        $team = Team::factory()->create();
        $subscription = Subscription::factory()->for($team)->create([
            'current_period_start' => CarbonImmutable::parse('2026-10-01 00:00:00'),
            'current_period_end' => CarbonImmutable::parse('2026-11-01 00:00:00'),
        ]);

        $this->service->markPastDue($subscription);

        $paidAt = CarbonImmutable::parse('2026-11-10 09:00:00');
        $activated = $this->service->activate($subscription, BillingInterval::Monthly, paidAt: $paidAt);

        $this->assertSame(SubscriptionStatus::Active, $activated->status);
        $this->assertTrue($paidAt->equalTo($activated->current_period_start));
        $this->assertTrue($paidAt->addMonthNoOverflow()->equalTo($activated->current_period_end));
    }

    public function test_an_unpaid_period_moves_to_past_due_and_keeps_access(): void
    {
        $subscription = Subscription::factory()->for(Team::factory())->active()->create();

        $this->service->markPastDue($subscription);

        $this->assertSame(SubscriptionStatus::PastDue, $subscription->status);
        $this->assertTrue($subscription->grantsAccess());
        $this->assertTrue($subscription->isCurrent());
    }

    public function test_a_grace_period_that_runs_out_expires_the_subscription(): void
    {
        $subscription = Subscription::factory()->for(Team::factory())->pastDue()->create();
        $expiredAt = CarbonImmutable::parse('2026-12-08 00:00:00');

        $this->service->markExpired($subscription, $expiredAt);

        $this->assertSame(SubscriptionStatus::Expired, $subscription->status);
        $this->assertFalse($subscription->grantsAccess());
        $this->assertTrue($expiredAt->equalTo($subscription->ended_at));
    }

    public function test_a_trial_that_runs_out_expires(): void
    {
        $subscription = Subscription::factory()->for(Team::factory())->trialing()->create();

        $this->service->markExpired($subscription);

        $this->assertSame(SubscriptionStatus::Expired, $subscription->status);
        $this->assertNotNull($subscription->ended_at);
    }

    public function test_canceling_a_trial_ends_it_immediately(): void
    {
        $subscription = Subscription::factory()->for(Team::factory())->trialing()->create();
        $canceledAt = CarbonImmutable::parse('2026-12-02 12:00:00');

        $this->service->cancel($subscription, $canceledAt);

        $this->assertSame(SubscriptionStatus::Canceled, $subscription->status);
        $this->assertTrue($canceledAt->equalTo($subscription->canceled_at));
        $this->assertTrue($canceledAt->equalTo($subscription->ended_at));
        $this->assertFalse($subscription->grantsAccess());
    }

    public function test_canceling_a_paid_subscription_keeps_access_until_the_period_ends(): void
    {
        $subscription = Subscription::factory()->for(Team::factory())->active()->create();
        $requestedAt = CarbonImmutable::parse('2026-11-20 10:00:00');

        $this->service->cancel($subscription, $requestedAt);

        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertTrue($requestedAt->equalTo($subscription->canceled_at));
        $this->assertNull($subscription->ended_at);
        $this->assertTrue($subscription->grantsAccess());

        // A second request does not move the recorded date.
        $this->service->cancel($subscription, $requestedAt->addDays(2));

        $this->assertTrue($requestedAt->equalTo($subscription->canceled_at));
    }

    public function test_the_period_close_finishes_a_canceled_subscription(): void
    {
        $subscription = Subscription::factory()->for(Team::factory())->active()->create();
        $this->service->cancel($subscription);

        $closedAt = CarbonImmutable::parse('2026-12-11 00:00:00');
        $this->service->markCanceled($subscription, $closedAt);

        $this->assertSame(SubscriptionStatus::Canceled, $subscription->status);
        $this->assertTrue($closedAt->equalTo($subscription->ended_at));
        $this->assertFalse($subscription->canTransitionTo(SubscriptionStatus::Active));
    }

    public function test_a_trial_cannot_jump_straight_to_past_due(): void
    {
        $subscription = Subscription::factory()->for(Team::factory())->trialing()->create();

        $this->expectException(InvalidSubscriptionTransition::class);

        $this->service->markPastDue($subscription);
    }

    public function test_an_expired_subscription_cannot_be_reactivated(): void
    {
        $subscription = Subscription::factory()->for(Team::factory())->expired()->create();

        $this->expectException(InvalidSubscriptionTransition::class);

        $this->service->activate($subscription, BillingInterval::Monthly);
    }
}
