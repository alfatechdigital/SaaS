<?php

namespace Tests\Feature\Billing;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\Team;
use App\Support\TenantContext;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The `subscriptions` table has to enforce the two rules the service will rely on
 * — one running subscription per tenant, and confinement to the active tenant —
 * rather than trusting every future writer to remember them.
 */
class SubscriptionTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_tenant_cannot_hold_two_running_subscriptions(): void
    {
        $team = Team::factory()->create();

        $this->forTeam($team, fn () => Subscription::factory()->for($team)->active()->create());

        $this->expectException(QueryException::class);

        $this->forTeam($team, fn () => Subscription::factory()->for($team)->trialing()->create());
    }

    public function test_an_ended_subscription_leaves_room_for_the_next_one(): void
    {
        $team = Team::factory()->create();

        $this->forTeam($team, fn () => Subscription::factory()->for($team)->canceled()->create());
        $replacement = $this->forTeam($team, fn () => Subscription::factory()->for($team)->active()->create());

        $current = $this->forTeam($team, fn () => $team->currentSubscription);

        $this->assertNotNull($current);
        $this->assertSame($replacement->id, $current->id);
    }

    public function test_status_is_an_enum_on_the_model_and_its_value_in_the_database(): void
    {
        $team = Team::factory()->create();

        $subscription = $this->forTeam($team, fn () => Subscription::factory()->for($team)->pastDue()->create());

        $status = $this->forTeam($team, function () use ($subscription): SubscriptionStatus {
            $subscription->refresh();

            return $subscription->status;
        });

        $this->assertSame(SubscriptionStatus::PastDue, $status);
        $this->assertSame(
            'past_due',
            DB::table('subscriptions')->where('id', $subscription->id)->value('status'),
        );
    }

    public function test_a_trial_runs_until_its_trial_end_and_grants_access(): void
    {
        $team = Team::factory()->create();

        $subscription = $this->forTeam($team, fn () => Subscription::factory()->for($team)->trialing()->create());

        $this->assertTrue($subscription->onTrial());
        $this->assertTrue($subscription->grantsAccess());
        $this->assertTrue($subscription->isCurrent());
        $this->assertNotNull($subscription->trial_ends_at);
        $this->assertTrue($subscription->canTransitionTo(SubscriptionStatus::Active));
        $this->assertFalse($subscription->canTransitionTo(SubscriptionStatus::PastDue));
    }

    public function test_subscriptions_are_confined_to_the_active_tenant(): void
    {
        $mine = Team::factory()->create();
        $theirs = Team::factory()->create();

        $this->forTeam($mine, fn () => Subscription::factory()->for($mine)->active()->create(['plan_code' => 'plus']));
        $this->forTeam($theirs, fn () => Subscription::factory()->for($theirs)->trialing()->create(['plan_code' => 'pro']));

        $visible = $this->forTeam($mine, fn () => Subscription::query()->pluck('plan_code')->all());

        $this->assertSame(['plus'], $visible);
    }

    /**
     * Run a callback with an explicit tenant.
     *
     * Subscriptions are tenant-owned, so the team global scope refuses to query
     * them without a context — exactly as in a queued job or a console command.
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
