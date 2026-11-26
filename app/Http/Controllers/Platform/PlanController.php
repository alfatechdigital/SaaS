<?php

namespace App\Http\Controllers\Platform;

use App\Actions\Platform\RecordPlatformAudit;
use App\Enums\Feature;
use App\Enums\PlatformAuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\UpdatePlanRequest;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The platform panel's plan list (F-8, B-2 tugas 26 Nov).
 *
 * Two halves with different sources of truth, and the page says so:
 *
 * - **Commercial** (price, description, on sale) lives in the `plans` table and is
 *   editable here, so a price change needs no deployment.
 * - **Contract** (features, quotas) lives in `config/plans.php` (ADR-14, D-06) and
 *   is shown read-only. Offering an edit box for it would promise runtime changes
 *   that the entitlement layer would ignore.
 */
class PlanController extends Controller
{
    /**
     * List every plan with its commercial and contractual sides.
     */
    public function index(): Response
    {
        /** @var array<string, array{name: string, features: array<int, string>, limits: array<string, int>}> $config */
        $config = config('plans.plans');

        return Inertia::render('platform/plans/Index', [
            'plans' => Plan::query()
                ->orderBy('price_monthly')
                ->get()
                ->map(fn (Plan $plan): array => [
                    'id' => $plan->id,
                    'code' => $plan->code,
                    'name' => $plan->name,
                    'description' => $plan->description,
                    'priceMonthly' => $plan->price_monthly,
                    'priceYearly' => $plan->price_yearly,
                    'isActive' => $plan->is_active,
                    'isDefault' => $plan->code === config('plans.default_plan'),
                    'features' => $this->featureLabels($config[$plan->code]['features'] ?? []),
                    'limits' => $config[$plan->code]['limits'] ?? [],
                ])
                ->all(),
        ]);
    }

    /**
     * Save a plan's commercial terms.
     */
    public function update(UpdatePlanRequest $request, Plan $plan, RecordPlatformAudit $audit): RedirectResponse
    {
        $plan->update([
            'description' => $request->validated('description'),
            'price_monthly' => (int) $request->validated('price_monthly'),
            'price_yearly' => $request->validated('price_yearly') === null
                ? null
                : (int) $request->validated('price_yearly'),
            'is_active' => $request->boolean('is_active'),
        ]);

        $audit->handle(PlatformAuditAction::PlanUpdated, $plan, [
            'code' => $plan->code,
            'price_monthly' => $plan->price_monthly,
            'price_yearly' => $plan->price_yearly,
            'is_active' => $plan->is_active,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Paket ":name" diperbarui.', ['name' => $plan->name]),
        ]);

        return to_route('platform.plans.index');
    }

    /**
     * Turn stored feature values into display labels, skipping unknown ones.
     *
     * @param  array<int, string>  $values
     * @return array<int, array{value: string, label: string}>
     */
    private function featureLabels(array $values): array
    {
        $features = [];

        foreach ($values as $value) {
            $feature = Feature::tryFrom($value);

            if ($feature !== null) {
                $features[] = ['value' => $feature->value, 'label' => $feature->label()];
            }
        }

        return $features;
    }
}
