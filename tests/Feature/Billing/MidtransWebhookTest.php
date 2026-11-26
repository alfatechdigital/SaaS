<?php

namespace Tests\Feature\Billing;

use App\Enums\InvoiceStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Invoice;
use App\Models\PaymentEvent;
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
 * The Midtrans webhook (B-2, tugas 24 Nov): verified, idempotent, and the only path
 * that turns a provider's word into money on an invoice.
 */
class MidtransWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const SERVER_KEY = 'SB-Mid-server-test';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'billing.driver' => 'midtrans',
            'billing.midtrans.server_key' => self::SERVER_KEY,
        ]);
    }

    public function test_a_settlement_settles_the_invoice_and_activates_the_subscription(): void
    {
        [$invoice, $subscription, $team] = $this->trialWithRenewalInvoice();

        $this->postJson('/webhooks/midtrans', $this->payload($invoice))->assertOk();

        $settled = $this->forTeam($team, fn (): Invoice => Invoice::query()->whereKey($invoice->id)->sole());
        $activated = $this->forTeam($team, fn (): Subscription => Subscription::query()->whereKey($subscription->id)->sole());
        $event = PaymentEvent::query()->sole();

        $this->assertSame(InvoiceStatus::Paid, $settled->status);
        $this->assertSame('midtrans-tx-1', $settled->provider_reference);
        $this->assertNotNull($settled->paid_at);
        $this->assertSame(SubscriptionStatus::Active, $activated->status);
        $this->assertTrue($settled->period_start->equalTo($activated->current_period_start));
        $this->assertTrue($settled->period_end->equalTo($activated->current_period_end));
        $this->assertTrue($event->isProcessed());
        $this->assertNull($event->error);
    }

    public function test_a_duplicate_delivery_changes_nothing(): void
    {
        [$invoice, , $team] = $this->trialWithRenewalInvoice();
        $payload = $this->payload($invoice);

        $this->postJson('/webhooks/midtrans', $payload)->assertOk();

        $paidAt = $this->forTeam($team, fn () => Invoice::query()->whereKey($invoice->id)->sole()->paid_at);

        $this->postJson('/webhooks/midtrans', $payload)->assertOk();

        $afterDuplicate = $this->forTeam($team, fn (): Invoice => Invoice::query()->whereKey($invoice->id)->sole());

        $this->assertSame(1, PaymentEvent::query()->count());
        $this->assertSame(1, $this->forTeam($team, fn (): int => Invoice::query()->count()));
        $this->assertTrue($paidAt->equalTo($afterDuplicate->paid_at));
    }

    public function test_a_settlement_for_an_already_active_subscription_does_not_move_its_period(): void
    {
        [$invoice, $subscription, $team] = $this->trialWithRenewalInvoice();

        $this->postJson('/webhooks/midtrans', $this->payload($invoice))->assertOk();
        $periodEnd = $this->forTeam($team, fn () => Subscription::query()->whereKey($subscription->id)->sole()->current_period_end);

        // A second transaction for the same invoice (e.g. a retried settlement with a
        // new id) must not extend the paid period again.
        $this->postJson('/webhooks/midtrans', $this->payload($invoice, [
            'transaction_id' => 'midtrans-tx-2',
        ]))->assertOk();

        $after = $this->forTeam($team, fn (): Subscription => Subscription::query()->whereKey($subscription->id)->sole());

        $this->assertTrue($periodEnd->equalTo($after->current_period_end));
        $this->assertSame(SubscriptionStatus::Active, $after->status);
    }

    public function test_a_bad_signature_is_refused(): void
    {
        [$invoice, , $team] = $this->trialWithRenewalInvoice();

        $this->postJson('/webhooks/midtrans', $this->payload($invoice, [], 'wrong-key'))
            ->assertForbidden();

        $this->assertSame(InvoiceStatus::Open, $this->forTeam($team, fn (): Invoice => Invoice::query()->whereKey($invoice->id)->sole())->status);
        $this->assertSame(0, PaymentEvent::query()->count());
    }

    public function test_a_missing_server_key_answers_service_unavailable(): void
    {
        config(['billing.midtrans.server_key' => '']);

        [$invoice, , $team] = $this->trialWithRenewalInvoice();

        $this->postJson('/webhooks/midtrans', $this->payload($invoice))
            ->assertStatus(503);

        $this->assertSame(InvoiceStatus::Open, $this->forTeam($team, fn (): Invoice => Invoice::query()->whereKey($invoice->id)->sole())->status);
        $this->assertSame(0, PaymentEvent::query()->count());
    }

    public function test_an_unknown_order_is_accepted_without_recording_anything(): void
    {
        [$invoice] = $this->trialWithRenewalInvoice();

        $payload = $this->payload($invoice, ['order_id' => 'INV-TIDAK-ADA']);

        $this->postJson('/webhooks/midtrans', $payload)->assertOk();

        $this->assertSame(0, PaymentEvent::query()->count());
    }

    public function test_an_amount_mismatch_never_settles_the_invoice(): void
    {
        [$invoice, , $team] = $this->trialWithRenewalInvoice();

        $this->postJson('/webhooks/midtrans', $this->payload($invoice, ['gross_amount' => '1.00']))
            ->assertOk();

        $event = PaymentEvent::query()->sole();

        $this->assertSame(InvoiceStatus::Open, $this->forTeam($team, fn (): Invoice => Invoice::query()->whereKey($invoice->id)->sole())->status);
        $this->assertTrue($event->isProcessed());
        $this->assertStringContainsString('Amount mismatch', (string) $event->error);
    }

    public function test_an_expired_transaction_voids_the_invoice(): void
    {
        [$invoice, , $team] = $this->trialWithRenewalInvoice();

        $this->postJson('/webhooks/midtrans', $this->payload($invoice, [
            'transaction_status' => 'expire',
        ]))->assertOk();

        $this->assertSame(InvoiceStatus::Void, $this->forTeam($team, fn (): Invoice => Invoice::query()->whereKey($invoice->id)->sole())->status);
        $this->assertTrue(PaymentEvent::query()->sole()->isProcessed());
    }

    public function test_a_denied_transaction_voids_the_invoice(): void
    {
        [$invoice, , $team] = $this->trialWithRenewalInvoice();

        $this->postJson('/webhooks/midtrans', $this->payload($invoice, [
            'transaction_status' => 'deny',
        ]))->assertOk();

        $this->assertSame(InvoiceStatus::Void, $this->forTeam($team, fn (): Invoice => Invoice::query()->whereKey($invoice->id)->sole())->status);
    }

    public function test_a_pending_transaction_changes_nothing(): void
    {
        [$invoice, , $team] = $this->trialWithRenewalInvoice();

        $this->postJson('/webhooks/midtrans', $this->payload($invoice, [
            'transaction_status' => 'pending',
            'fraud_status' => null,
        ]))->assertOk();

        $this->assertSame(InvoiceStatus::Open, $this->forTeam($team, fn (): Invoice => Invoice::query()->whereKey($invoice->id)->sole())->status);
        $this->assertTrue(PaymentEvent::query()->sole()->isProcessed());
    }

    public function test_a_refund_leaves_the_paid_invoice_and_subscription_alone(): void
    {
        [$invoice, $subscription, $team] = $this->trialWithRenewalInvoice();

        $this->postJson('/webhooks/midtrans', $this->payload($invoice))->assertOk();

        $this->postJson('/webhooks/midtrans', $this->payload($invoice, [
            'transaction_status' => 'refund',
        ]))->assertOk();

        $this->assertSame(InvoiceStatus::Paid, $this->forTeam($team, fn (): Invoice => Invoice::query()->whereKey($invoice->id)->sole())->status);
        $this->assertSame(SubscriptionStatus::Active, $this->forTeam($team, fn (): Subscription => Subscription::query()->whereKey($subscription->id)->sole())->status);
        $this->assertSame(2, PaymentEvent::query()->count());
    }

    public function test_an_incomplete_payload_is_a_bad_request(): void
    {
        $this->postJson('/webhooks/midtrans', ['order_id' => 'INV-20261231-1'])
            ->assertStatus(400);

        $this->assertSame(0, PaymentEvent::query()->count());
    }

    /**
     * A trial with the renewal invoice a period check would have issued.
     *
     * @return array{Invoice, Subscription, Team}
     */
    private function trialWithRenewalInvoice(): array
    {
        $team = Team::factory()->create();

        $subscription = app(SubscriptionService::class)->startTrial(
            $team,
            'plus',
            CarbonImmutable::parse('2026-12-01 00:00:00'),
        );

        $invoice = app(InvoiceService::class)->issueNext($subscription);

        return [$invoice, $subscription, $team];
    }

    /**
     * A signed Midtrans notification for the invoice.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(Invoice $invoice, array $overrides = [], string $serverKey = self::SERVER_KEY): array
    {
        $payload = array_merge([
            'order_id' => $invoice->number,
            'status_code' => '200',
            'gross_amount' => number_format($invoice->amount, 2, '.', ''),
            'transaction_status' => 'settlement',
            'transaction_id' => 'midtrans-tx-1',
            'fraud_status' => 'accept',
            'payment_type' => 'bank_transfer',
        ], $overrides);

        $payload['signature_key'] = hash(
            'sha512',
            $payload['order_id'].$payload['status_code'].$payload['gross_amount'].$serverKey,
        );

        return $payload;
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
