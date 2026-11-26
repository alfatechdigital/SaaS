<?php

namespace Tests\Feature\Billing;

use App\Enums\BillingInterval;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\Team;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * `billing:reconcile` is the schedule entry point: it must refuse to pretend it
 * reconciled when no provider is configured, and otherwise report what it did.
 */
class ReconcileBillingCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_refuses_to_run_without_a_provider(): void
    {
        config(['billing.driver' => 'none']);

        $this->artisan('billing:reconcile')
            ->expectsOutputToContain('BILLING_DRIVER=none')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_it_reports_what_the_pass_found(): void
    {
        config([
            'billing.driver' => 'midtrans',
            'billing.midtrans.server_key' => 'SB-Mid-server-test',
            'billing.midtrans.is_production' => false,
        ]);

        $invoice = $this->invoice();

        Http::fake([
            '*' => Http::response([
                'transaction_status' => 'settlement',
                'transaction_id' => 'trx-1',
                'fraud_status' => 'accept',
                'gross_amount' => '349000.00',
            ]),
        ]);

        $this->artisan('billing:reconcile')
            ->expectsOutputToContain('1 lunas')
            ->assertSuccessful();

        $settled = $this->forTeam($invoice->team, fn (): Invoice => Invoice::query()->whereKey($invoice->id)->sole());

        $this->assertSame(InvoiceStatus::Paid, $settled->status);
    }

    private function invoice(): Invoice
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
        ]);
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
