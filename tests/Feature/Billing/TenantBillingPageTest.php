<?php

namespace Tests\Feature\Billing;

use App\Enums\BillingInterval;
use App\Enums\InvoiceStatus;
use App\Enums\TeamRole;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The tenant's "Paket saya" page and its single action (B-2, tugas 26 Nov).
 *
 * Reading is open to every member — knowing what the tenant pays for is not a
 * privileged secret — while paying an invoice is owner-only. The payment link is
 * created once and stored, so pressing the button twice cannot open a second
 * transaction.
 */
class TenantBillingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_shows_the_plan_features_quotas_and_invoices(): void
    {
        $this->seed(PlanSeeder::class);

        [$owner, $team] = $this->tenant(TeamRole::Owner, 'plus');
        $this->invoice($team);

        $this->actingAs($owner)
            ->get($this->billingUrl($team))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/settings/Billing')
                ->where('plan.code', 'plus')
                ->where('plan.name', 'Plus')
                ->where('plan.priceMonthly', 349000)
                ->where('usage.users.limit', 3)
                ->where('usage.users.used', 1)
                ->where('paymentEnabled', false)
                ->has('plan.features')
                ->has('addons')
                ->has('invoices', 1),
            );
    }

    public function test_every_member_may_read_the_page(): void
    {
        [$member, $team] = $this->tenant(TeamRole::Member, 'plus');

        $this->actingAs($member)
            ->get($this->billingUrl($team))
            ->assertOk();
    }

    public function test_the_owner_can_open_the_provider_payment_page(): void
    {
        $this->configureMidtrans();

        Http::fake([
            'https://app.sandbox.midtrans.com/snap/v1/transactions' => Http::response([
                'token' => 'snap-token-1',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/snap-token-1',
            ], 201),
        ]);

        [$owner, $team] = $this->tenant(TeamRole::Owner, 'plus');
        $invoice = $this->invoice($team);

        $this->actingAs($owner)
            ->post($this->payUrl($team, $invoice))
            ->assertRedirect('https://app.sandbox.midtrans.com/snap/v4/redirection/snap-token-1');

        $this->assertSame(
            'https://app.sandbox.midtrans.com/snap/v4/redirection/snap-token-1',
            $invoice->fresh()?->payment_url,
        );
    }

    public function test_pressing_pay_twice_reuses_the_same_transaction(): void
    {
        $this->configureMidtrans();

        Http::fake([
            '*' => Http::response([
                'token' => 'snap-token-1',
                'redirect_url' => 'https://app.sandbox.midtrans.com/snap/v4/redirection/snap-token-1',
            ], 201),
        ]);

        [$owner, $team] = $this->tenant(TeamRole::Owner, 'plus');
        $invoice = $this->invoice($team);

        $this->actingAs($owner)->post($this->payUrl($team, $invoice))->assertRedirect();
        $this->actingAs($owner)->post($this->payUrl($team, $invoice))->assertRedirect();

        Http::assertSentCount(1);
    }

    public function test_a_paid_invoice_cannot_be_paid_again(): void
    {
        $this->configureMidtrans();

        [$owner, $team] = $this->tenant(TeamRole::Owner, 'plus');
        $invoice = $this->invoice($team);
        $invoice->update(['status' => InvoiceStatus::Paid, 'paid_at' => now()]);

        $this->actingAs($owner)
            ->post($this->payUrl($team, $invoice))
            ->assertRedirect();

        Http::assertNothingSent();
        $this->assertNull($invoice->fresh()?->payment_url);
    }

    public function test_a_member_cannot_pay(): void
    {
        $this->configureMidtrans();

        [$member, $team] = $this->tenant(TeamRole::Member, 'plus');
        $invoice = $this->invoice($team);

        $this->actingAs($member)
            ->post($this->payUrl($team, $invoice))
            ->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_another_tenants_invoice_is_not_found(): void
    {
        $this->configureMidtrans();

        [$owner, $team] = $this->tenant(TeamRole::Owner, 'plus');
        [, $otherTeam] = $this->tenant(TeamRole::Owner, 'plus');

        $foreign = $this->invoice($otherTeam);

        $this->actingAs($owner)
            ->post($this->payUrl($team, $foreign))
            ->assertNotFound();

        Http::assertNothingSent();
    }

    public function test_payment_is_refused_when_no_provider_is_configured(): void
    {
        config(['billing.driver' => 'none']);

        [$owner, $team] = $this->tenant(TeamRole::Owner, 'plus');
        $invoice = $this->invoice($team);

        $this->actingAs($owner)
            ->post($this->payUrl($team, $invoice))
            ->assertRedirect();

        Http::assertNothingSent();
        $this->assertNull($invoice->fresh()?->payment_url);
    }

    private function configureMidtrans(): void
    {
        config([
            'billing.driver' => 'midtrans',
            'billing.midtrans.server_key' => 'SB-Mid-server-test',
            'billing.midtrans.is_production' => false,
            'billing.midtrans.timeout' => 5,
        ]);
    }

    /**
     * A tenant with one member in the given role and a paid subscription.
     *
     * @return array{0: User, 1: Team}
     */
    private function tenant(TeamRole $role, string $planCode): array
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $team->members()->attach($user, ['role' => $role->value]);
        $user->switchTeam($team);

        Subscription::factory()->for($team)->active()->create(['plan_code' => $planCode]);

        return [$user, $team];
    }

    private function invoice(Team $team): Invoice
    {
        $subscription = Subscription::query()->withoutTeamScope()->where('team_id', $team->id)->sole();

        $periodStart = CarbonImmutable::now()->addDay();

        return Invoice::factory()->for($subscription)->create([
            'billing_interval' => BillingInterval::Monthly,
            'amount' => 349000,
            'period_start' => $periodStart,
            'period_end' => $periodStart->addMonthNoOverflow(),
            'due_at' => $periodStart,
        ]);
    }

    private function billingUrl(Team $team): string
    {
        return route('billing.show', ['current_team' => $team->slug]);
    }

    private function payUrl(Team $team, Invoice $invoice): string
    {
        return route('billing.pay', [
            'current_team' => $team->slug,
            'invoice' => $invoice->id,
        ]);
    }
}
