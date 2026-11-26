<?php

namespace App\Http\Controllers\Platform;

use App\Actions\Platform\RecordPlatformAudit;
use App\Enums\Feature;
use App\Enums\PlatformAuditAction;
use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Team;
use App\Models\TenantAddon;
use App\Support\Billing\MonthlyQuota;
use App\Support\Billing\SeatQuota;
use App\Support\Billing\TenantAddons;
use App\Support\Entitlements;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One tenant's billing, as the platform operator sees it (F-8, B-2 tugas 26 Nov).
 *
 * The panel answers two questions the tenant cannot answer about itself:
 *
 * 1. **What does this tenant effectively have?** Plan features ∪ active add-ons,
 *    plus the quotas it is held to — the same numbers the tenant's own "Paket saya"
 *    page shows, read through the same services so the two can never disagree.
 * 2. **What did the operator change?** Granting or revoking an add-on is the only
 *    entitlement change made here; the plan itself comes from the subscription, and
 *    upgrade/downgrade stays a gelombang-2 feature (G-2).
 */
class TenantBillingController extends Controller
{
    /**
     * Show the tenant's subscription, entitlement and add-ons.
     */
    public function show(Team $team, TenantAddons $addons, SeatQuota $seats, MonthlyQuota $quota): Response
    {
        $entitlements = app(Entitlements::class);

        $planCode = $entitlements->planCode($team);
        $plan = $entitlements->plan($team);
        $planFeatures = $plan['features'];

        $addonRows = $addons->all($team);
        $activeFeatures = array_map(
            fn (Feature $feature): string => $feature->value,
            $addons->active($team),
        );

        $subscription = app(TenantContext::class)->runFor(
            $team,
            fn () => $team->currentSubscription,
        );

        return Inertia::render('platform/tenants/Billing', [
            'tenant' => [
                'id' => $team->id,
                'name' => $team->name,
                'slug' => $team->slug,
                'suspended' => $team->isSuspended(),
            ],
            'plan' => [
                'code' => $planCode,
                'name' => $plan['name'],
                'priceMonthly' => Plan::findByCode($planCode)?->price_monthly,
                'priceYearly' => Plan::findByCode($planCode)?->price_yearly,
                'features' => $this->labels($planFeatures),
                'limits' => $plan['limits'],
            ],
            'entitlement' => [
                'features' => $this->labels($entitlements->features($team)),
                'addonFeatures' => $this->labels($activeFeatures),
            ],
            'subscription' => $subscription === null
                ? null
                : [
                    'status' => $subscription->status->value,
                    'statusLabel' => $subscription->status->label(),
                    'planCode' => $subscription->plan_code,
                    'interval' => $subscription->billing_interval?->value,
                    'trialEndsAt' => $subscription->trial_ends_at?->toISOString(),
                    'periodStart' => $subscription->current_period_start->toISOString(),
                    'periodEnd' => $subscription->current_period_end->toISOString(),
                    'canceledAt' => $subscription->canceled_at?->toISOString(),
                ],
            'addons' => array_map(fn (TenantAddon $addon): array => [
                'feature' => $addon->feature,
                'label' => Feature::tryFrom($addon->feature)?->label() ?? $addon->feature,
                'startsAt' => $addon->starts_at->toISOString(),
                'endsAt' => $addon->ends_at?->toISOString(),
                'active' => $addon->isActive(),
            ], $addonRows),
            'grantableFeatures' => $this->labels(
                array_values(array_map(
                    fn (Feature $feature): string => $feature->value,
                    array_filter(
                        Feature::cases(),
                        fn (Feature $feature): bool => ! in_array($feature->value, $planFeatures, true)
                            && ! in_array($feature->value, $activeFeatures, true),
                    ),
                )),
            ),
            'usage' => [
                'users' => ['used' => $seats->used($team), 'limit' => $seats->limit($team)],
                'ai' => [
                    'used' => $quota->used($team, MonthlyQuota::AI_GENERATIONS),
                    'limit' => $quota->limit($team, MonthlyQuota::AI_GENERATIONS),
                    'period' => $quota->period(),
                ],
            ],
            'invoices' => app(TenantContext::class)->runFor(
                $team,
                fn () => Invoice::query()
                    ->orderByDesc('period_start')
                    ->limit(6)
                    ->get()
                    ->map(fn (Invoice $invoice): array => [
                        'number' => $invoice->number,
                        'status' => $invoice->status->value,
                        'statusLabel' => $invoice->status->label(),
                        'amount' => $invoice->amount,
                        'periodStart' => $invoice->period_start->toISOString(),
                        'dueAt' => $invoice->due_at->toISOString(),
                        'paidAt' => $invoice->paid_at?->toISOString(),
                    ])
                    ->all(),
            ),
        ]);
    }

    /**
     * Grant one feature to the tenant as an add-on.
     */
    public function storeAddon(Request $request, Team $team, TenantAddons $addons, RecordPlatformAudit $audit): RedirectResponse
    {
        $value = $request->validate([
            'feature' => ['required', 'string', Rule::enum(Feature::class)],
        ])['feature'];

        $feature = Feature::from($value);

        // Selling a feature the plan already includes would be charging twice for
        // the same thing, so it is refused instead of silently recorded.
        if ($addons->coveredByPlan($team, $feature)) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Fitur :feature sudah termasuk paket tenant ini.', ['feature' => $feature->label()]),
            ]);

            return back();
        }

        $addons->grant($team, $feature);

        $audit->handle(PlatformAuditAction::AddonGranted, $team, [
            'feature' => $feature->value,
            'name' => $team->name,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Add-on :feature diberikan.', ['feature' => $feature->label()]),
        ]);

        return back();
    }

    /**
     * End one add-on now.
     */
    public function destroyAddon(Team $team, string $feature, TenantAddons $addons, RecordPlatformAudit $audit): RedirectResponse
    {
        $resolved = Feature::tryFrom($feature);

        abort_if($resolved === null, 404);

        $addons->revoke($team, $resolved);

        $audit->handle(PlatformAuditAction::AddonRevoked, $team, [
            'feature' => $resolved->value,
            'name' => $team->name,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Add-on :feature dicabut.', ['feature' => $resolved->label()]),
        ]);

        return back();
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
