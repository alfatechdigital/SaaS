<?php

namespace Tests\Feature\Billing;

use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Enums\TeamRole;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\User;
use App\Notifications\Billing\SubscriptionPaymentReminder;
use App\Support\Billing\SubscriptionPeriodChecker;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The daily clock: what happens when a period, a trial, or a grace period ends.
 */
class SubscriptionPeriodCheckerTest extends TestCase
{
    use RefreshDatabase;

    private SubscriptionPeriodChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->checker = app(SubscriptionPeriodChecker::class);
    }

    public function test_an_active_period_that_ended_bills_the_next_one_and_starts_the_grace_period(): void
    {
        [$team, $owner] = $this->tenantWithOwner();
        $subscription = Subscription::factory()->for($team)->active()->create([
            'plan_code' => 'plus',
            'current_period_start' => CarbonImmutable::parse('2026-11-01 00:00:00'),
            'current_period_end' => CarbonImmutable::parse('2026-12-01 00:00:00'),
        ]);

        $this->checker->check($subscription, CarbonImmutable::parse('2026-12-01 00:30:00'));

        $invoice = $this->forTeam($team, fn (): Invoice => $subscription->invoices()->sole());

        $this->assertSame(SubscriptionStatus::PastDue, $subscription->status);
        $this->assertSame(InvoiceStatus::Open, $invoice->status);
        $this->assertSame(349000, $invoice->amount);
        $this->assertTrue($subscription->current_period_end->equalTo($invoice->period_start));
        $this->assertSame(1, $invoice->reminders_sent);
        Notification::assertSentTo($owner, SubscriptionPaymentReminder::class);
    }

    public function test_a_period_that_has_not_ended_is_left_alone(): void
    {
        [$team] = $this->tenantWithOwner();
        $subscription = Subscription::factory()->for($team)->active()->create([
            'current_period_end' => CarbonImmutable::parse('2026-12-10 00:00:00'),
        ]);

        $this->checker->check($subscription, CarbonImmutable::parse('2026-12-01 00:30:00'));

        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertSame(0, $this->forTeam($team, fn (): int => $subscription->invoices()->count()));
    }

    public function test_the_three_reminders_each_wait_for_their_day(): void
    {
        [$team, $owner] = $this->tenantWithOwner();
        $subscription = Subscription::factory()->for($team)->active()->create([
            'current_period_start' => CarbonImmutable::parse('2026-11-01 00:00:00'),
            'current_period_end' => CarbonImmutable::parse('2026-12-01 00:00:00'),
        ]);

        $this->checker->check($subscription, CarbonImmutable::parse('2026-12-01 00:30:00'));
        $this->checker->check($subscription, CarbonImmutable::parse('2026-12-01 18:00:00'));
        Notification::assertSentToTimes($owner, SubscriptionPaymentReminder::class, 1);

        $this->checker->check($subscription, CarbonImmutable::parse('2026-12-02 00:30:00'));
        Notification::assertSentToTimes($owner, SubscriptionPaymentReminder::class, 1);

        // Day 3 and day 6 after the due date (1 December) — never late enough to
        // collide with the 8 December expiry.
        $this->checker->check($subscription, CarbonImmutable::parse('2026-12-04 00:30:00'));
        Notification::assertSentToTimes($owner, SubscriptionPaymentReminder::class, 2);

        $this->checker->check($subscription, CarbonImmutable::parse('2026-12-07 00:30:00'));
        $this->checker->check($subscription, CarbonImmutable::parse('2026-12-07 23:00:00'));
        Notification::assertSentToTimes($owner, SubscriptionPaymentReminder::class, 3);
    }

    public function test_a_subscription_expires_when_the_grace_period_ends(): void
    {
        [$team] = $this->tenantWithOwner();
        $subscription = Subscription::factory()->for($team)->pastDue()->create([
            'current_period_start' => CarbonImmutable::parse('2026-11-01 00:00:00'),
            'current_period_end' => CarbonImmutable::parse('2026-12-01 00:00:00'),
        ]);

        $expiredAt = CarbonImmutable::parse('2026-12-08 00:30:00');
        $this->checker->check($subscription, $expiredAt);

        $this->assertSame(SubscriptionStatus::Expired, $subscription->status);
        $this->assertTrue($expiredAt->equalTo($subscription->ended_at));
        $this->assertFalse($subscription->grantsAccess());
    }

    public function test_a_canceled_subscription_ends_at_period_close_without_an_invoice(): void
    {
        [$team, $owner] = $this->tenantWithOwner();
        $subscription = Subscription::factory()->for($team)->active()->create([
            'current_period_start' => CarbonImmutable::parse('2026-11-01 00:00:00'),
            'current_period_end' => CarbonImmutable::parse('2026-12-01 00:00:00'),
            'canceled_at' => CarbonImmutable::parse('2026-11-15 00:00:00'),
        ]);

        $this->checker->check($subscription, CarbonImmutable::parse('2026-12-01 00:30:00'));

        $this->assertSame(SubscriptionStatus::Canceled, $subscription->status);
        $this->assertNotNull($subscription->ended_at);
        $this->assertSame(0, $this->forTeam($team, fn (): int => $subscription->invoices()->count()));
        Notification::assertNothingSentTo($owner);
    }

    public function test_a_trial_that_ran_out_expires_without_an_invoice(): void
    {
        [$team, $owner] = $this->tenantWithOwner();
        $subscription = Subscription::factory()->for($team)->trialing()->create([
            'trial_ends_at' => CarbonImmutable::parse('2026-12-01 00:00:00'),
            'current_period_start' => CarbonImmutable::parse('2026-11-01 00:00:00'),
            'current_period_end' => CarbonImmutable::parse('2026-12-01 00:00:00'),
        ]);

        $this->checker->check($subscription, CarbonImmutable::parse('2026-12-01 00:30:00'));

        $this->assertSame(SubscriptionStatus::Expired, $subscription->status);
        $this->assertSame(0, $this->forTeam($team, fn (): int => $subscription->invoices()->count()));
        Notification::assertNothingSentTo($owner);
    }

    public function test_a_past_due_subscription_without_an_invoice_is_given_one_to_chase(): void
    {
        [$team, $owner] = $this->tenantWithOwner();
        $subscription = Subscription::factory()->for($team)->pastDue()->create([
            'plan_code' => 'pro',
            'current_period_start' => CarbonImmutable::parse('2026-11-01 00:00:00'),
            'current_period_end' => CarbonImmutable::parse('2026-12-01 00:00:00'),
        ]);

        $this->checker->check($subscription, CarbonImmutable::parse('2026-12-02 00:30:00'));

        $invoice = $this->forTeam($team, fn (): Invoice => $subscription->invoices()->sole());

        $this->assertSame(SubscriptionStatus::PastDue, $subscription->status);
        $this->assertSame(InvoiceStatus::Open, $invoice->status);
        $this->assertSame(799000, $invoice->amount);
        $this->assertSame(1, $invoice->reminders_sent);
        Notification::assertSentTo($owner, SubscriptionPaymentReminder::class);
    }

    /**
     * @return array{Team, User}
     */
    private function tenantWithOwner(): array
    {
        $team = Team::factory()->create();
        $owner = User::factory()->create();
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        return [$team, $owner];
    }

    /**
     * Run a callback with the team's context active.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    private function forTeam(Team $team, Closure $callback): mixed
    {
        return app(TenantContext::class)->runFor($team, $callback);
    }
}
