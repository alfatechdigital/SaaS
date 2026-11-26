<?php

namespace App\Http\Controllers;

use App\Enums\Feature;
use App\Exceptions\InvoiceNotPayable;
use App\Exceptions\PaymentGatewayError;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Team;
use App\Support\Billing\InvoicePayment;
use App\Support\Billing\MonthlyQuota;
use App\Support\Billing\SeatQuota;
use App\Support\Billing\TenantAddons;
use App\Support\CurrentTeam;
use App\Support\Entitlements;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * The tenant's own "Paket saya" page (B-2, tugas 26 Nov).
 *
 * It answers the three questions a paying tenant asks, from the same services that
 * enforce the answers elsewhere — so the page cannot promise something the server
 * would refuse:
 *
 * - **What am I on?** plan, status, and when the current period ends.
 * - **What do I get, and how much is left?** effective features (plan ∪ add-ons)
 *   and the quotas that are actually enforced ({@see SeatQuota}, {@see MonthlyQuota}).
 * - **What do I owe?** the invoices of this tenant, with a button that opens the
 *   provider's payment page once.
 *
 * No upgrade/downgrade: that is a gelombang-2 feature (G-2), so the page says the
 * plan is managed by the operator instead of offering a button that goes nowhere.
 */
class TenantBillingController extends Controller
{
    /**
     * Show the tenant's plan, entitlement, usage and invoices.
     */
    public function show(Request $request, SeatQuota $seats, MonthlyQuota $quota): Response
    {
        $team = CurrentTeam::from($request);

        $entitlements = app(Entitlements::class);
        $planCode = $entitlements->planCode($team);
        $plan = $entitlements->plan($team);
        $planRow = Plan::findByCode($planCode);

        $subscription = $team->currentSubscription;

        return Inertia::render('admin/settings/Billing', [
            'plan' => [
                'code' => $planCode,
                'name' => $plan['name'],
                'description' => $planRow?->description,
                'priceMonthly' => $planRow?->price_monthly,
                'priceYearly' => $planRow?->price_yearly,
                'features' => $this->labels($entitlements->features($team)),
                'limits' => $plan['limits'],
            ],
            'addons' => $this->labels(array_map(
                fn (Feature $feature): string => $feature->value,
                app(TenantAddons::class)->active($team),
            )),
            'subscription' => $subscription === null
                ? null
                : [
                    'status' => $subscription->status->value,
                    'statusLabel' => $subscription->status->label(),
                    'interval' => $subscription->billing_interval?->value,
                    'trialEndsAt' => $subscription->trial_ends_at?->toISOString(),
                    'periodStart' => $subscription->current_period_start->toISOString(),
                    'periodEnd' => $subscription->current_period_end->toISOString(),
                    'canceledAt' => $subscription->canceled_at?->toISOString(),
                ],
            'usage' => [
                'users' => [
                    'used' => $seats->used($team),
                    'limit' => $seats->limit($team),
                ],
                'ai' => [
                    'used' => $quota->used($team, MonthlyQuota::AI_GENERATIONS),
                    'limit' => $quota->limit($team, MonthlyQuota::AI_GENERATIONS),
                    'period' => $quota->period(),
                ],
            ],
            'invoices' => Invoice::query()
                ->orderByDesc('period_start')
                ->limit(12)
                ->get()
                ->map(fn (Invoice $invoice): array => [
                    'id' => $invoice->id,
                    'number' => $invoice->number,
                    'status' => $invoice->status->value,
                    'statusLabel' => $invoice->status->label(),
                    'amount' => $invoice->amount,
                    'periodStart' => $invoice->period_start->toISOString(),
                    'periodEnd' => $invoice->period_end->toISOString(),
                    'dueAt' => $invoice->due_at->toISOString(),
                    'paidAt' => $invoice->paid_at?->toISOString(),
                    'isOpen' => $invoice->isOpen(),
                    'hasPaymentLink' => is_string($invoice->payment_url) && $invoice->payment_url !== '',
                ])
                ->all(),
            // The button is hidden — not merely failing later — when no provider is
            // configured (BILLING_DRIVER=none), which is the default everywhere but
            // a deliberately configured installation.
            'paymentEnabled' => (string) config('billing.driver') !== 'none',
        ]);
    }

    /**
     * Open the payment page for one of the tenant's invoices.
     *
     * The link is created once and stored on the invoice ({@see InvoicePayment}),
     * so pressing the button twice cannot open a second transaction for the same
     * period. Only the owner may pay: asking someone else's money to leave is not a
     * member-level action.
     */
    public function pay(Request $request): RedirectResponse|SymfonyResponse
    {
        $team = CurrentTeam::from($request);

        abort_unless($request->user()?->ownsTeam($team) === true, 403);

        // Resolved here, not by implicit binding: `invoices` is tenant-owned and the
        // team scope refuses to answer before `CurrentTeam::from()` has run, which is
        // the same reason the domain controllers look their models up themselves.
        $invoice = Invoice::query()->findOrFail((int) $request->route('invoice'));

        try {
            $payable = app(InvoicePayment::class)->start($invoice);
        } catch (InvoiceNotPayable $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return back();
        } catch (PaymentGatewayError $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $exception->getMessage()]);

            return back();
        }

        return Inertia::location((string) $payable->payment_url);
    }

    /**
     * Map feature values to display labels, skipping unknown ones.
     *
     * @param  array<int, string>  $values
     * @return array<int, array{value: string, label: string}>
     */
    private function labels(array $values): array
    {
        $labels = [];

        foreach ($values as $value) {
            $feature = Feature::tryFrom($value);

            if ($feature !== null) {
                $labels[] = ['value' => $feature->value, 'label' => $feature->label()];
            }
        }

        return $labels;
    }
}
