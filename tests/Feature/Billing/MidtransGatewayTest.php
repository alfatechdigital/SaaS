<?php

namespace Tests\Feature\Billing;

use App\Enums\BillingInterval;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Exceptions\InvoiceNotPayable;
use App\Exceptions\PaymentGatewayError;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\Team;
use App\Support\Billing\InvoicePayment;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Midtrans driver (B-2, tugas 23 Nov): what we send, what we persist, and how the
 * provider's vocabulary is translated. No network — `Http::fake()` stands in.
 */
class MidtransGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_transaction_sends_the_invoice_and_stores_the_link(): void
    {
        $this->configureMidtrans();

        Http::fake([
            'https://api.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
                'token' => 'snap-token-1',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/snap-token-1',
            ], 201),
        ]);

        $invoice = $this->invoice();

        $paid = app(InvoicePayment::class)->start($invoice);

        $this->assertSame('snap-token-1', $paid->payment_token);
        $this->assertSame(
            'https://app.sandbox.midtrans.com/snap/v4/redirection/snap-token-1',
            $paid->payment_url,
        );

        Http::assertSent(function (Request $request) use ($invoice): bool {
            $payload = $request->data();

            return $request->url() === 'https://api.sandbox.midtrans.com/snap/v1/transactions'
                && $request->hasHeader('Authorization', 'Basic '.base64_encode('SB-Mid-server-test:'))
                && $payload['transaction_details'] === [
                    'order_id' => $invoice->number,
                    'gross_amount' => 349000,
                ]
                && $payload['item_details'][0]['price'] === 349000
                && $payload['expiry'] === ['unit' => 'days', 'duration' => 7];
        });
    }

    public function test_asking_for_the_link_twice_does_not_open_a_second_transaction(): void
    {
        $this->configureMidtrans();

        Http::fake([
            '*' => Http::response([
                'token' => 'snap-token-1',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/snap-token-1',
            ], 201),
        ]);

        $invoice = $this->invoice();
        $first = app(InvoicePayment::class)->start($invoice);
        $second = app(InvoicePayment::class)->start($first);

        $this->assertSame($first->payment_url, $second->payment_url);
        Http::assertSentCount(1);
    }

    public function test_production_requires_the_switch_and_points_at_the_live_host(): void
    {
        $this->configureMidtrans(production: true);

        Http::fake([
            'https://api.midtrans.com/snap/v1/transactions' => Http::response([
                'token' => 'snap-token-2',
                'redirect_url' => 'https://app.midtrans.com/snap/v4/redirection/snap-token-2',
            ], 201),
        ]);

        app(InvoicePayment::class)->start($this->invoice());

        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.midtrans.com/snap/v1/transactions');
    }

    public function test_a_paid_or_voided_invoice_cannot_be_paid_again(): void
    {
        $this->configureMidtrans();
        Http::fake();

        foreach ([InvoiceStatus::Paid, InvoiceStatus::Void] as $status) {
            $invoice = $this->invoice();
            $invoice->status = $status;
            $invoice->save();

            try {
                app(InvoicePayment::class)->start($invoice);
                $this->fail("A [{$status->value}] invoice must not be payable.");
            } catch (InvoiceNotPayable) {
                $this->assertTrue(true);
            }
        }

        Http::assertNothingSent();
    }

    public function test_an_unconfigured_gateway_refuses_instead_of_pretending(): void
    {
        config(['billing.driver' => 'none']);
        Http::fake();

        $this->expectException(PaymentGatewayError::class);

        app(InvoicePayment::class)->start($this->invoice());
    }

    public function test_a_missing_server_key_fails_before_any_request(): void
    {
        $this->configureMidtrans();
        config(['billing.midtrans.server_key' => '']);
        Http::fake();

        try {
            app(InvoicePayment::class)->start($this->invoice());
            $this->fail('Midtrans must not be called without a server key.');
        } catch (PaymentGatewayError $exception) {
            $this->assertStringContainsString('MIDTRANS_SERVER_KEY', $exception->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_a_refused_request_becomes_a_gateway_error_without_leaking_the_key(): void
    {
        $this->configureMidtrans();

        Http::fake([
            '*' => Http::response(['status_message' => 'Invalid gross_amount'], 406),
        ]);

        try {
            app(InvoicePayment::class)->start($this->invoice());
            $this->fail('A refused request must not look like a payment link.');
        } catch (PaymentGatewayError $exception) {
            $this->assertStringContainsString('Invalid gross_amount', $exception->getMessage());
            $this->assertStringNotContainsString('SB-Mid-server-test', $exception->getMessage());
        }
    }

    public function test_an_unexpected_response_is_an_error_not_a_link(): void
    {
        $this->configureMidtrans();

        Http::fake(['*' => Http::response(['token' => ''], 201)]);

        $this->expectException(PaymentGatewayError::class);

        app(InvoicePayment::class)->start($this->invoice());
    }

    public function test_status_is_fetched_by_our_invoice_number(): void
    {
        $this->configureMidtrans();
        $invoice = $this->invoice();

        Http::fake([
            '*' => Http::response([
                'transaction_status' => 'settlement',
                'transaction_id' => 'midtrans-tx-1',
            ]),
        ]);

        $result = app(InvoicePayment::class)->refresh($invoice);

        $this->assertSame(PaymentStatus::Paid, $result->status);
        $this->assertSame('midtrans-tx-1', $result->transactionId);
        $this->assertSame(InvoiceStatus::Open, $invoice->status, 'Reading a status must not change the invoice.');

        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/v2/'.$invoice->number.'/status'));
    }

    public function test_every_midtrans_status_is_translated(): void
    {
        $this->configureMidtrans();

        $cases = [
            ['transaction_status' => 'settlement', 'fraud_status' => null, 'expected' => PaymentStatus::Paid],
            ['transaction_status' => 'capture', 'fraud_status' => 'accept', 'expected' => PaymentStatus::Paid],
            ['transaction_status' => 'capture', 'fraud_status' => 'challenge', 'expected' => PaymentStatus::Pending],
            ['transaction_status' => 'pending', 'fraud_status' => null, 'expected' => PaymentStatus::Pending],
            ['transaction_status' => 'authorize', 'fraud_status' => null, 'expected' => PaymentStatus::Pending],
            ['transaction_status' => 'deny', 'fraud_status' => null, 'expected' => PaymentStatus::Failed],
            ['transaction_status' => 'cancel', 'fraud_status' => null, 'expected' => PaymentStatus::Failed],
            ['transaction_status' => 'failure', 'fraud_status' => null, 'expected' => PaymentStatus::Failed],
            ['transaction_status' => 'expire', 'fraud_status' => null, 'expected' => PaymentStatus::Expired],
            ['transaction_status' => 'refund', 'fraud_status' => null, 'expected' => PaymentStatus::Refunded],
            ['transaction_status' => 'something-new', 'fraud_status' => null, 'expected' => PaymentStatus::Pending],
        ];

        // One fake per order id: `Http::fake()` merges stubs, so a single wildcard
        // would let the first case answer every lookup.
        $stubs = [];
        $invoices = [];

        foreach ($cases as $index => $case) {
            $number = 'INV-STATUS-'.$index;
            $invoices[$index] = $this->invoice($number);
            $stubs['https://api.sandbox.midtrans.com/v2/'.$number.'/status'] = Http::response([
                'transaction_status' => $case['transaction_status'],
                'fraud_status' => $case['fraud_status'],
            ]);
        }

        Http::fake($stubs);

        foreach ($invoices as $index => $invoice) {
            $this->assertSame(
                $cases[$index]['expected'],
                app(InvoicePayment::class)->refresh($invoice)->status,
                "Status [{$cases[$index]['transaction_status']}] was translated incorrectly.",
            );
        }
    }

    public function test_a_status_lookup_that_fails_is_an_error(): void
    {
        $this->configureMidtrans();

        Http::fake(['*' => Http::response(['status_message' => 'Transaction not found'], 404)]);

        $this->expectException(PaymentGatewayError::class);

        app(InvoicePayment::class)->refresh($this->invoice());
    }

    private function configureMidtrans(bool $production = false): void
    {
        config([
            'billing.driver' => 'midtrans',
            'billing.midtrans.server_key' => 'SB-Mid-server-test',
            'billing.midtrans.is_production' => $production,
            'billing.midtrans.timeout' => 5,
        ]);
    }

    private function invoice(?string $number = null): Invoice
    {
        $subscription = Subscription::factory()
            ->for(Team::factory())
            ->active()
            ->create(['plan_code' => 'plus']);

        $periodStart = CarbonImmutable::parse('2026-12-01 00:00:00');

        return Invoice::factory()->for($subscription)->create([
            'number' => $number ?? 'INV-20261201-'.$subscription->id,
            'billing_interval' => BillingInterval::Monthly,
            'amount' => 349000,
            'period_start' => $periodStart,
            'period_end' => $periodStart->addMonthNoOverflow(),
            'due_at' => $periodStart,
        ]);
    }
}
