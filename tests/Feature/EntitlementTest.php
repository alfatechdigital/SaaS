<?php

namespace Tests\Feature;

use App\Contracts\TeamEntitlementSource;
use App\Enums\Feature;
use App\Exceptions\UnknownPlan;
use App\Models\Team;
use App\Support\Entitlements;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\FakeTeamEntitlementSource;
use Tests\Support\FeatureGuardProbe;
use Tests\TestCase;

/**
 * Entitlement resolution (Fase 4, tugas 4.3.6 dan 4.3.7).
 *
 * No database on purpose: entitlement is a pure read of config plus whatever the
 * entitlement source reports, so these tests stay fast and independent of the
 * billing tables that do not exist yet.
 */
class EntitlementTest extends TestCase
{
    private FakeTeamEntitlementSource $source;

    protected function setUp(): void
    {
        parent::setUp();

        $this->source = new FakeTeamEntitlementSource;

        $this->app->instance(TeamEntitlementSource::class, $this->source);
        $this->app->forgetInstance(Entitlements::class);
    }

    private function tenant(): Team
    {
        return Team::factory()->make();
    }

    public function test_a_tenant_without_a_subscription_falls_back_to_the_default_plan(): void
    {
        $tenant = $this->tenant();

        $this->assertSame(config('plans.default_plan'), app(Entitlements::class)->planCode($tenant));
        $this->assertTrue($tenant->can(Feature::CompanyProfile));
        $this->assertTrue($tenant->can(Feature::ContactForm));
        $this->assertFalse($tenant->can(Feature::Finance));
    }

    public function test_a_basic_tenant_cannot_use_plus_or_pro_features(): void
    {
        $this->source->planCode = 'basic';
        $tenant = $this->tenant();

        $this->assertFalse($tenant->can(Feature::LeadManagement));
        $this->assertFalse($tenant->can(Feature::WhatsAppBot));
        $this->assertFalse($tenant->can(Feature::PaymentGateway));
        $this->assertFalse($tenant->can(Feature::ProjectManagement));
        $this->assertFalse($tenant->can(Feature::ContentAi));
    }

    public function test_a_plus_tenant_gains_plus_features_but_not_pro_modules(): void
    {
        $this->source->planCode = 'plus';
        $tenant = $this->tenant();

        $this->assertTrue($tenant->can(Feature::LeadManagement));
        $this->assertTrue($tenant->can(Feature::WhatsAppBot));
        $this->assertTrue($tenant->can(Feature::CompanyProfile));
        $this->assertFalse($tenant->can(Feature::ProjectManagement));
        $this->assertFalse($tenant->can(Feature::TaskManagement));
        $this->assertFalse($tenant->can(Feature::Finance));
    }

    public function test_a_pro_tenant_can_use_every_feature_of_every_plan(): void
    {
        $this->source->planCode = 'pro';
        $tenant = $this->tenant();

        foreach (Feature::cases() as $feature) {
            $this->assertTrue(
                $tenant->can($feature),
                "Plan [pro] should include [{$feature->value}].",
            );
        }
    }

    public function test_features_lists_the_plan_contents_in_config_order(): void
    {
        $this->source->planCode = 'plus';

        $this->assertSame(
            config('plans.plans.plus.features'),
            $this->tenant()->features(),
        );
    }

    public function test_an_active_addon_grants_a_feature_the_plan_does_not_include(): void
    {
        $this->source->planCode = 'plus';
        $this->source->addonFeatures = [Feature::Finance];

        $tenant = $this->tenant();

        $this->assertNotContains(Feature::Finance->value, config('plans.plans.plus.features'));
        $this->assertTrue($tenant->can(Feature::Finance));
        $this->assertContains(Feature::Finance->value, $tenant->features());
    }

    public function test_an_addon_that_repeats_a_plan_feature_does_not_duplicate_it(): void
    {
        $this->source->planCode = 'pro';
        $this->source->addonFeatures = [Feature::Finance, Feature::CompanyProfile];

        $features = $this->tenant()->features();

        $this->assertSame($features, array_unique($features));
        $this->assertSame(config('plans.plans.pro.features'), $features);
    }

    public function test_a_plan_missing_from_config_fails_loudly(): void
    {
        $this->source->planCode = 'enterprise';

        $this->expectException(UnknownPlan::class);

        app(Entitlements::class)->planCode($this->tenant());
    }

    public function test_limits_come_from_the_plan(): void
    {
        $entitlements = app(Entitlements::class);
        $tenant = $this->tenant();

        $this->source->planCode = 'basic';
        $this->assertSame(1, $entitlements->limit($tenant, 'users'));
        $this->assertSame(0, $entitlements->limit($tenant, 'ai_generations_per_month'));

        $this->source->planCode = 'pro';
        $this->assertSame(10, $entitlements->limit($tenant, 'users'));
        $this->assertSame(50, $entitlements->limit($tenant, 'ai_generations_per_month'));

        $this->assertNull($entitlements->limit($tenant, 'storage_gb'));
    }

    public function test_the_backend_guard_rejects_a_feature_the_plan_lacks(): void
    {
        $this->source->planCode = 'basic';

        try {
            (new FeatureGuardProbe)->guard($this->tenant(), Feature::Finance);
            $this->fail('Authorizing an unavailable feature should have aborted with 403.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_the_backend_guard_lets_an_entitled_tenant_through(): void
    {
        $this->source->planCode = 'basic';

        (new FeatureGuardProbe)->guard($this->tenant(), Feature::CompanyProfile);

        $this->assertTrue(true);
    }
}
