<?php

namespace Tests\Feature\Billing;

use App\Enums\BillingInterval;
use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\Team;
use App\Support\Billing\InvoiceReconciler;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Reconciliation (B-2, tugas 25 Nov): a payment whose webhook never arrived must
 * still reach the invoice, without touching anything that is already settled.
 */
class InvoiceReconcilerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_settles_an_invoice_the_webhook_never_reported(): void
    {
        $this->configureMidtrans();

        $invoice = $this->invoice();
        $this->fakeSettlement($invoice, 'trx-1');

        $report = app(InvoiceReconciler::class)->reconcile();

        $this->assertSame(1, $report->checked);
        $this->assertSame(1, $report->settled);
        $this->assertSame(0, $report->failed);

        $settled = $this->forTeam($invoice->team, fn (): Invoice => Invoice::query()->whereKey($invoice->id)->sole());

        $this->assertSame(InvoiceStatus::Paid, $settled->status);
        $this->assertSame('trx-1', $settled->provider_reference);

        $subscription = $this->subscriptionOf($invoice);

        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertTrue($subscription->current_period_start->equalTo($invoice->period_start));
        $this->assertSame('trx-1', $subscription->provider_reference);
    }

    public function test_an_expired_transaction_voids_the_invoice_and_leaves_the_subscription_alone(): void
    {
        $this->configureMidtrans();

        $invoice = $this->invoice();

        Http::fake([
            '*' => Http::response([
                'transaction_status' => 'expire',
                'transaction_id' => 'trx-expired',
                'status_code' => '407',
                'gross_amount' => '349000.00',
            ]),
        ]);

        $report = app(InvoiceReconciler::class)->reconcile();

        $this->assertSame(1, $report->closed);

        $voided = $this->forTeam($invoice->team, fn (): Invoice => Invoice::query()->whereKey($invoice->id)->sole());

        $this->assertSame(InvoiceStatus::Void, $voided->status);
        $this->assertSame(SubscriptionStatus::PastDue, $this->subscriptionOf($invoice)->status);
    }

    public function test_a_pending_transaction_changes_nothing(): void
    {
        $this->configureMidtrans();

        $invoice = $this->invoice();

        Http::fake([
            '*' => Http::response([
                'transaction_status' => 'pending',
                'transaction_id' => 'trx-pending',
                'gross_amount' => '349000.00',
            ]),
        ]);

        $report = app(InvoiceReconciler::class)->reconcile();

        $this->assertSame(1, $report->pending);
        $this->assertSame(InvoiceStatus::Open, $this->forTeam($invoice->team, fn (): Invoice => Invoice::query()->whereKey($invoice->id)->sole())->status);
    }

    public function test_invoices_without_a_payment_link_are_never_asked_about(): void
    {
        $this->configureMidtrans();

        $this->invoice(['payment_url' => null, 'payment_token' => null]);

        $report = app(InvoiceReconciler::class)->reconcile();

        $this->assertSame(0, $report->checked);
        Http::assertNothingSent();
    }

    public function test_a_failing_lookup_does_not_stop_the_other_invoices(): void
    {
        $this->configureMidtrans();

        $unreadable = $this->invoice();
        $settled = $this->invoice();

        Http::fake(function (Request $request) use ($unreadable, $settled) {
            if (str_contains($request->url(), rawurlencode($unreadable->number))) {
                return Http::response(['status_message' => 'Midtrans is having a bad day'], 500);
            }

            return Http::response([
                'transaction_status' => 'settlement',
                'transaction_id' => 'trx-'.$settled->id,
                'fraud_status' => 'accept',
                'gross_amount' => '349000.00',
            ]);
        });

        $report = app(InvoiceReconciler::class)->reconcile();

        $this->assertSame(2, $report->checked);
        $this->assertSame(1, $report->failed);
        $this->assertSame(1, $report->settled);
        $this->assertSame(InvoiceStatus::Open, $this->forTeam($unreadable->team, fn (): Invoice => Invoice::query()->whereKey($unreadable->id)->sole())->status);
        $this->assertSame(InvoiceStatus::Paid, $this->forTeam($settled->team, fn (): Invoice => Invoice::query()->whereKey($settled->id)->sole())->status);
    }

    public function test_a_second_pass_does_not_touch_a_settled_invoice(): void
    {
        $this->configureMidtrans();

        $invoice = $this->invoice();
        $this->fakeSettlement($invoice, 'trx-1');

        $reconciler = app(InvoiceReconciler::class);
        $reconciler->reconcile();
        $second = $reconciler->reconcile();

        $this->assertSame(0, $second->checked);
        Http::assertSentCount(1);
    }

    public function test_invoices_older_than_the_window_are_left_alone(): void
    {
        $this->configureMidtrans();

        $invoice = $this->invoice();
        DB::table('invoices')->where('id', $invoice->id)->update(['updated_at' => now()->subDays(45)]);

        $report = app(InvoiceReconciler::class)->reconcile();

        $this->assertSame(0, $report->checked);
        Http::assertNothingSent();
    }

    private function configureMidtrans(): void
    {
        config([
            'billing.driver' => 'midtrans',
            'billing.midtrans.server_key' => 'SB-Mid-server-test',
            'billing.midtrans.is_production' => false,
            'billing.midtrans.timeout' => 5,
            'billing.reconciliation.window_days' => 30,
            'billing.reconciliation.batch' => 100,
        ]);
    }

    /**
     * An unpaid invoice with a live payment link: the only row reconciliation
     * should care about.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function invoice(array $overrides = []): Invoice
    {
        $subscription = Subscription::factory()
            ->for(Team::factory())
            ->pastDue()
            ->create(['plan_code' => 'plus']);

        $periodStart = CarbonImmutable::now()->addDay();

        return Invoice::factory()->for($subscription)->create([
            'billing_interval' => BillingInterval::Monthly,
            'amount' => 349000,
            'period_start' => $periodStart,
            'period_end' => $periodStart->addMonthNoOverflow(),
            'due_at' => $periodStart,
            'payment_token' => 'snap-token',
            'payment_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/snap-token',
            ...$overrides,
        ]);
    }

    private function fakeSettlement(Invoice $invoice, string $transactionId): void
    {
        Http::fake([
            'https://api.sandbox.midtrans.com/v2/'.rawurlencode($invoice->number).'/status' => Http::response([
                'transaction_status' => 'settlement',
                'transaction_id' => $transactionId,
                'fraud_status' => 'accept',
                'status_code' => '200',
                'gross_amount' => '349000.00',
            ]),
        ]);
    }

    private function subscriptionOf(Invoice $invoice): Subscription
    {
        return $this->forTeam(
            $invoice->team,
            fn (): Subscription => Subscription::query()->whereKey($invoice->subscription_id)->sole(),
        );
    }

    /**
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
