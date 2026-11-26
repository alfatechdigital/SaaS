<?php

namespace Tests\Feature\Billing;

use App\Enums\BillingInterval;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\Team;
use App\Support\Billing\InvoiceService;
use App\Support\Billing\SubscriptionService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Invoices are the record of what a tenant owes for which period, so the rules
 * here are the ones that prevent double billing.
 */
class InvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    private InvoiceService $invoices;

    protected function setUp(): void
    {
        parent::setUp();

        $this->invoices = app(InvoiceService::class);
    }

    public function test_the_next_period_is_invoiced_at_the_config_price(): void
    {
        $team = Team::factory()->create();
        $subscription = Subscription::factory()->for($team)->active()->create([
            'plan_code' => 'plus',
            'current_period_end' => CarbonImmutable::parse('2026-12-01 00:00:00'),
        ]);

        $invoice = $this->invoices->issueNext($subscription);

        $this->assertSame(InvoiceStatus::Open, $invoice->status);
        $this->assertSame(BillingInterval::Monthly, $invoice->billing_interval);
        $this->assertSame(349000, $invoice->amount);
        $this->assertSame('IDR', $invoice->currency);
        $this->assertSame('midtrans', $invoice->provider);
        $this->assertSame('INV-20261201-'.$subscription->id, $invoice->number);
        $this->assertTrue($subscription->current_period_end->equalTo($invoice->period_start));
        $this->assertTrue($subscription->current_period_end->addMonthNoOverflow()->equalTo($invoice->period_end));
        $this->assertTrue($invoice->period_start->equalTo($invoice->due_at));
        $this->assertNull($invoice->paid_at);
    }

    public function test_invoicing_the_same_period_twice_returns_one_invoice(): void
    {
        $team = Team::factory()->create();
        $subscription = Subscription::factory()->for($team)->active()->create();

        $first = $this->invoices->issueNext($subscription);
        $second = $this->invoices->issueNext($subscription);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $this->forTeam($team, fn (): int => $subscription->invoices()->count()));
    }

    public function test_a_yearly_subscription_is_invoiced_at_the_yearly_price(): void
    {
        $team = Team::factory()->create();
        $subscription = Subscription::factory()->for($team)->active()->create([
            'plan_code' => 'pro',
            'billing_interval' => BillingInterval::Yearly,
            'current_period_end' => CarbonImmutable::parse('2027-01-15 00:00:00'),
        ]);

        $invoice = $this->invoices->issueNext($subscription);

        $this->assertSame(BillingInterval::Yearly, $invoice->billing_interval);
        $this->assertSame(7990000, $invoice->amount);
        $this->assertTrue(
            $subscription->current_period_end->addYearNoOverflow()->equalTo($invoice->period_end),
        );
    }

    public function test_a_trial_is_invoiced_from_its_trial_end(): void
    {
        $team = Team::factory()->create();
        $trial = Subscription::factory()->for($team)->trialing()->create([
            'plan_code' => 'plus',
            'trial_ends_at' => CarbonImmutable::parse('2026-12-31 00:00:00'),
            'current_period_end' => CarbonImmutable::parse('2026-12-31 00:00:00'),
        ]);

        $invoice = $this->invoices->issueNext($trial);

        // A trial has no purchased interval yet; the first bill is monthly.
        $this->assertSame(BillingInterval::Monthly, $invoice->billing_interval);
        $this->assertSame(349000, $invoice->amount);
        $this->assertTrue($trial->trial_ends_at->equalTo($invoice->period_start));
    }

    public function test_activation_records_the_paid_period_as_an_invoice(): void
    {
        $team = Team::factory()->create();
        $subscriptions = app(SubscriptionService::class);

        $trial = $subscriptions->startTrial($team, 'plus', CarbonImmutable::parse('2026-12-01 08:00:00'));
        $trialEnd = $trial->trial_ends_at;
        $paidAt = CarbonImmutable::parse('2026-12-20 09:30:00');
        $activated = $subscriptions->activate($trial, BillingInterval::Monthly, 'midtrans-order-9', $paidAt);

        $invoice = $this->forTeam($team, fn (): Invoice => $activated->invoices()->sole());

        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame(349000, $invoice->amount);
        $this->assertSame('midtrans-order-9', $invoice->provider_reference);
        $this->assertTrue($paidAt->equalTo($invoice->paid_at));
        $this->assertTrue($trialEnd->equalTo($invoice->period_start));
        $this->assertTrue($trialEnd->addMonthNoOverflow()->equalTo($invoice->period_end));
    }

    public function test_a_late_payment_voids_the_invoice_it_replaced(): void
    {
        $team = Team::factory()->create();
        $subscriptions = app(SubscriptionService::class);

        $subscription = Subscription::factory()->for($team)->active()->create([
            'plan_code' => 'plus',
            'current_period_start' => CarbonImmutable::parse('2026-11-01 00:00:00'),
            'current_period_end' => CarbonImmutable::parse('2026-12-01 00:00:00'),
        ]);

        $renewal = $this->invoices->issueNext($subscription);
        $subscriptions->markPastDue($subscription);

        // Paid nine days late: the new period starts at the payment, so the invoice
        // issued for 1 December no longer describes what is owed.
        $subscriptions->activate(
            $subscription,
            BillingInterval::Monthly,
            'midtrans-order-10',
            CarbonImmutable::parse('2026-12-10 10:00:00'),
        );

        $invoices = $this->forTeam($team, fn () => $subscription->invoices()->orderBy('period_start')->get());
        $renewalStatus = $this->forTeam($team, fn (): InvoiceStatus => Invoice::query()->whereKey($renewal->id)->sole()->status);

        $this->assertSame(2, $invoices->count());
        $this->assertSame(InvoiceStatus::Void, $renewalStatus);
        $this->assertSame(InvoiceStatus::Paid, $invoices->last()->status);
        $this->assertTrue(
            CarbonImmutable::parse('2026-12-10 10:00:00')->equalTo($invoices->last()->period_start),
        );
    }

    public function test_marking_paid_twice_keeps_the_first_payment(): void
    {
        $team = Team::factory()->create();
        $subscription = Subscription::factory()->for($team)->active()->create();
        $invoice = $this->invoices->issueNext($subscription);

        $firstPaidAt = CarbonImmutable::parse('2026-12-01 08:00:00');
        $this->invoices->markPaid($invoice, $firstPaidAt, 'midtrans-order-1');

        $this->invoices->markPaid($invoice, $firstPaidAt->addDay(), 'midtrans-order-2');

        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertTrue($firstPaidAt->equalTo($invoice->paid_at));
        $this->assertSame('midtrans-order-1', $invoice->provider_reference);
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
