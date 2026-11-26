<?php

namespace Tests\Feature\Billing;

use App\Contracts\TeamEntitlementSource;
use App\Enums\Feature;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\Team;
use App\Support\Billing\SubscriptionEntitlementSource;
use App\Support\Entitlements;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Entitlement now reads the database: the tenant's running subscription decides the
 * plan, and a tenant without one falls back to `config('plans.default_plan')`.
 */
class SubscriptionEntitlementTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_billing_source_is_the_one_bound_in_the_application(): void
    {
        $this->assertInstanceOf(SubscriptionEntitlementSource::class, app(TeamEntitlementSource::class));
    }

    public function test_a_tenant_without_a_subscription_runs_on_the_default_plan(): void
    {
        $team = Team::factory()->create();

        $this->assertSame(config('plans.default_plan'), app(Entitlements::class)->planCode($team));
        $this->assertTrue($team->can(Feature::CompanyProfile));
        $this->assertFalse($team->can(Feature::LeadManagement));
    }

    public function test_a_running_subscription_decides_the_plan(): void
    {
        $team = Team::factory()->create();
        $this->subscribe($team, 'plus', SubscriptionStatus::Active);

        $this->assertTrue($team->can(Feature::LeadManagement));
        $this->assertFalse($team->can(Feature::Finance));
    }

    public function test_a_trial_grants_its_plan(): void
    {
        $team = Team::factory()->create();
        $this->subscribe($team, 'pro', SubscriptionStatus::Trialing);

        $this->assertTrue($team->can(Feature::Finance));
        $this->assertTrue($team->can(Feature::LeadManagement));
    }

    public function test_a_past_due_subscription_keeps_access_during_the_grace_period(): void
    {
        $team = Team::factory()->create();
        $this->subscribe($team, 'plus', SubscriptionStatus::PastDue);

        $this->assertTrue($team->can(Feature::LeadManagement));
    }

    public function test_an_expired_subscription_falls_back_to_the_default_plan(): void
    {
        $team = Team::factory()->create();
        $this->subscribe($team, 'pro', SubscriptionStatus::Expired);

        $this->assertSame(config('plans.default_plan'), app(Entitlements::class)->planCode($team));
        $this->assertFalse($team->can(Feature::Finance));
        $this->assertTrue($team->can(Feature::CompanyProfile));
    }

    public function test_the_running_subscription_wins_over_an_ended_one(): void
    {
        $team = Team::factory()->create();
        $this->subscribe($team, 'pro', SubscriptionStatus::Canceled);
        $this->subscribe($team, 'plus', SubscriptionStatus::Active);

        $this->assertTrue($team->can(Feature::LeadManagement));
        $this->assertFalse($team->can(Feature::Finance));
    }

    /**
     * Create a subscription for the team and nothing else.
     *
     * No tenant context is left active afterwards, which is the point: asking for
     * entitlements must not depend on a request being in flight.
     */
    private function subscribe(Team $team, string $planCode, SubscriptionStatus $status): Subscription
    {
        return app(TenantContext::class)->runFor($team, fn (): Subscription => Subscription::factory()
            ->for($team)
            ->create([
                'plan_code' => $planCode,
                'status' => $status,
                'ended_at' => $status->isFinal() ? now() : null,
            ]));
    }
}
