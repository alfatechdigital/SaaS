<?php

namespace Tests\Unit;

use App\Enums\Feature;
use Tests\TestCase;

/**
 * Guards `config/plans.php` against typos and drift from `App\Enums\Feature`.
 */
class PlansConfigTest extends TestCase
{
    /**
     * @return array<string, array<string, mixed>>
     */
    private function plans(): array
    {
        return config('plans.plans');
    }

    public function test_default_plan_points_to_a_defined_plan(): void
    {
        $default = config('plans.default_plan');

        $this->assertIsString($default);
        $this->assertArrayHasKey($default, $this->plans());
    }

    public function test_every_plan_declares_a_name_features_and_limits(): void
    {
        foreach ($this->plans() as $code => $plan) {
            $this->assertArrayHasKey('name', $plan, "Plan [{$code}] has no name.");
            $this->assertArrayHasKey('features', $plan, "Plan [{$code}] has no features.");
            $this->assertArrayHasKey('limits', $plan, "Plan [{$code}] has no limits.");
            $this->assertArrayHasKey('users', $plan['limits'], "Plan [{$code}] has no user quota.");
            $this->assertArrayHasKey('ai_generations_per_month', $plan['limits'], "Plan [{$code}] has no AI quota.");
        }
    }

    public function test_every_declared_feature_exists_in_the_enum(): void
    {
        foreach ($this->plans() as $code => $plan) {
            foreach ($plan['features'] as $feature) {
                $this->assertIsString($feature, "Plan [{$code}] has a non-string feature entry.");
                $this->assertNotNull(
                    Feature::tryFrom($feature),
                    "Plan [{$code}] lists [{$feature}], which is not an App\\Enums\\Feature value.",
                );
            }
        }
    }

    public function test_no_plan_lists_the_same_feature_twice(): void
    {
        foreach ($this->plans() as $code => $plan) {
            $this->assertSame(
                $plan['features'],
                array_unique($plan['features']),
                "Plan [{$code}] lists a feature more than once.",
            );
        }
    }

    public function test_every_feature_is_sold_by_at_least_one_plan(): void
    {
        $declared = array_merge(...array_values(array_map(
            fn (array $plan) => $plan['features'],
            $this->plans(),
        )));

        foreach (Feature::cases() as $feature) {
            $this->assertContains(
                $feature->value,
                $declared,
                "Feature [{$feature->value}] is not part of any plan.",
            );
        }
    }

    public function test_user_quotas_follow_the_agreed_matrix(): void
    {
        $this->assertSame(1, $this->plans()['basic']['limits']['users']);
        $this->assertSame(3, $this->plans()['plus']['limits']['users']);
        $this->assertSame(10, $this->plans()['pro']['limits']['users']);
    }

    public function test_higher_plans_include_the_lower_plan_features(): void
    {
        $basic = $this->plans()['basic']['features'];
        $plus = $this->plans()['plus']['features'];
        $pro = $this->plans()['pro']['features'];

        $this->assertSame([], array_values(array_diff($basic, $plus)), 'Plus is missing a Basic feature.');
        $this->assertSame([], array_values(array_diff($plus, $pro)), 'Pro is missing a Plus feature.');
    }

    public function test_module_features_stay_out_of_basic_and_plus(): void
    {
        $moduleFeatures = [
            Feature::ProjectManagement->value,
            Feature::TaskManagement->value,
            Feature::ContentPlanner->value,
            Feature::Finance->value,
        ];

        foreach (['basic', 'plus'] as $code) {
            foreach ($moduleFeatures as $feature) {
                $this->assertNotContains(
                    $feature,
                    $this->plans()[$code]['features'],
                    "Plan [{$code}] must not include the module feature [{$feature}].",
                );
            }
        }
    }
}
