<?php

namespace Tests\Feature\Billing;

use App\Enums\SubscriptionStatus;
use App\Enums\TeamRole;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\User;
use App\Support\TenantContext;
use Closure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The scheduled command has to work across tenants without an active request —
 * that is exactly the situation the team global scope guards against.
 */
class CheckSubscriptionPeriodCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_closes_every_tenants_due_period(): void
    {
        Notification::fake();

        $dueTeam = $this->tenantWithOwner();
        $futureTeam = $this->tenantWithOwner();

        $due = Subscription::factory()->for($dueTeam)->active()->create([
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now()->subDays(2),
        ]);

        $future = Subscription::factory()->for($futureTeam)->active()->create([
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        $this->artisan('subscriptions:check-period')->assertSuccessful();

        $this->assertSame(SubscriptionStatus::PastDue, $this->statusOf($dueTeam, $due));
        $this->assertSame(1, $this->invoiceCount($dueTeam, $due));
        $this->assertSame(SubscriptionStatus::Active, $this->statusOf($futureTeam, $future));
        $this->assertSame(0, $this->invoiceCount($futureTeam, $future));
    }

    public function test_running_it_twice_does_not_bill_or_remind_twice(): void
    {
        Notification::fake();

        $team = $this->tenantWithOwner();
        $subscription = Subscription::factory()->for($team)->active()->create([
            'current_period_start' => now()->subMonth(),
            'current_period_end' => now()->subDay(),
        ]);

        $this->artisan('subscriptions:check-period')->assertSuccessful();
        $this->artisan('subscriptions:check-period')->assertSuccessful();

        $invoice = $this->forTeam($team, fn (): Invoice => $subscription->invoices()->sole());

        $this->assertSame(1, $this->forTeam($team, fn (): int => $subscription->invoices()->count()));
        $this->assertSame(1, $invoice->reminders_sent);
    }

    private function tenantWithOwner(): Team
    {
        $team = Team::factory()->create();
        $owner = User::factory()->create();
        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);

        return $team;
    }

    private function statusOf(Team $team, Subscription $subscription): SubscriptionStatus
    {
        return $this->forTeam(
            $team,
            fn (): SubscriptionStatus => Subscription::query()->whereKey($subscription->id)->sole()->status,
        );
    }

    private function invoiceCount(Team $team, Subscription $subscription): int
    {
        return $this->forTeam(
            $team,
            fn (): int => $subscription->invoices()->count(),
        );
    }

    /**
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
