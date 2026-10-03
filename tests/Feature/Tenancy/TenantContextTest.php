<?php

namespace Tests\Feature\Tenancy;

use App\Data\UserTeam;
use App\Enums\TeamRole;
use App\Enums\TenantContextOrigin;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ResetTenantContext;
use App\Models\Team;
use App\Models\User;
use App\Support\TenantContext;
use Closure;
use Illuminate\Contracts\Queue\Job as QueueJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

/**
 * P-2 / tugas 2.1 — `TenantContext` as the single source of the active tenant.
 *
 * The dangerous property of a singleton context is leakage: in a queue worker or
 * a long-lived process, a tenant resolved for one unit of work must never still
 * be active for the next. Most of these tests exist to prove it cannot.
 *
 * @see \docs\implementation\phase-02-tenant-context.md tugas 2.1.1 s/d 2.1.5
 */
class TenantContextTest extends TestCase
{
    use RefreshDatabase;

    private TenantContext $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = app(TenantContext::class);
    }

    public function test_it_starts_without_an_active_tenant(): void
    {
        $this->assertNull($this->context->team());
        $this->assertNull($this->context->origin());
        $this->assertFalse($this->context->isResolved());
    }

    public function test_it_remembers_the_active_tenant_and_how_it_was_resolved(): void
    {
        $team = Team::factory()->create();

        $this->context->set($team, TenantContextOrigin::Console);

        $this->assertSame($team->id, $this->context->team()?->id);
        $this->assertSame(TenantContextOrigin::Console, $this->context->origin());
        $this->assertTrue($this->context->isResolved());
    }

    public function test_forget_clears_the_tenant_and_the_origin(): void
    {
        $this->context->set(Team::factory()->create(), TenantContextOrigin::Console);

        $this->context->forget();

        $this->assertNull($this->context->team());
        $this->assertNull($this->context->origin());
    }

    public function test_the_context_is_shared_through_the_container(): void
    {
        $team = Team::factory()->create();

        app(TenantContext::class)->set($team, TenantContextOrigin::Console);

        $this->assertSame($this->context, app(TenantContext::class));
        $this->assertSame($team->id, $this->context->team()?->id);
    }

    public function test_run_for_hands_the_tenant_to_the_callback_and_restores_it(): void
    {
        $outer = Team::factory()->create();
        $inner = Team::factory()->create();

        $this->context->set($outer, TenantContextOrigin::Path);

        $seen = $this->context->runFor($inner, function (Team $team): int {
            $this->assertSame($team->id, $this->context->team()?->id);

            return $team->id;
        });

        $this->assertSame($inner->id, $seen);

        // The previous context is restored, not merely forgotten: nesting must not
        // silently widen the outer scope.
        $this->assertSame($outer->id, $this->context->team()?->id);
        $this->assertSame(TenantContextOrigin::Path, $this->context->origin());
    }

    public function test_run_for_forgets_the_tenant_when_there_was_none(): void
    {
        $this->context->runFor(Team::factory()->create(), fn (): null => null);

        $this->assertNull($this->context->team());
        $this->assertNull($this->context->origin());
    }

    public function test_run_for_restores_the_context_even_when_the_callback_throws(): void
    {
        $outer = Team::factory()->create();
        $this->context->set($outer, TenantContextOrigin::Path);

        try {
            $this->context->runFor(Team::factory()->create(), function (): void {
                throw new RuntimeException('boom');
            });
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame($outer->id, $this->context->team()?->id);
    }

    public function test_the_reset_middleware_forgets_a_previously_active_tenant(): void
    {
        $this->context->set(Team::factory()->create(), TenantContextOrigin::Path);

        app(ResetTenantContext::class)->handle(Request::create('/'), fn () => response('ok'));

        $this->assertNull($this->context->team());
        $this->assertNull($this->context->origin());
    }

    public function test_a_queued_job_starting_forgets_the_active_tenant(): void
    {
        $this->context->set(Team::factory()->create(), TenantContextOrigin::Path);

        Event::dispatch(new JobProcessing('database', $this->createMock(QueueJob::class)));

        $this->assertNull($this->context->team());
    }

    public function test_resolving_a_team_route_activates_that_tenant(): void
    {
        [$team, $owner] = $this->teamWithOwner();

        $this->actingAs($owner)
            ->get(route('projects.index', ['current_team' => $team->slug]))
            ->assertOk();

        $this->assertSame($team->id, $this->context->team()?->id);
        $this->assertSame(TenantContextOrigin::Path, $this->context->origin());
    }

    /**
     * The end-to-end version of 2.1.4: two requests, one process.
     *
     * The second request never resolves a tenant, so a context that survived from
     * the first one would still be visible here.
     */
    public function test_the_active_tenant_does_not_leak_into_the_next_request(): void
    {
        [$team, $owner] = $this->teamWithOwner();

        $this->actingAs($owner)
            ->get(route('projects.index', ['current_team' => $team->slug]))
            ->assertOk();

        $this->assertSame($team->id, $this->context->team()?->id);

        $this->actingAs($owner)->get(route('home'))->assertOk();

        $this->assertNull($this->context->team());
    }

    /**
     * 2.1.5 — the shared prop must read the service, not the user's own team.
     *
     * Through a real request the two sources always agree (the membership
     * middleware switches the user to the visited team), so they are only
     * distinguishable by calling `share()` with the two deliberately out of sync.
     */
    public function test_the_shared_current_team_comes_from_the_active_tenant_context(): void
    {
        [$ownTeam, $user] = $this->teamWithOwner();
        $activeTeam = Team::factory()->create();

        $this->context->set($activeTeam, TenantContextOrigin::Console);

        $request = Request::create('/');
        $request->setUserResolver(fn (): User => $user);

        $this->assertNotSame($ownTeam->slug, $activeTeam->slug);
        $this->assertSame($activeTeam->slug, $this->sharedTeamSlug($request));
    }

    public function test_the_shared_current_team_falls_back_to_the_users_own_team(): void
    {
        [$ownTeam, $user] = $this->teamWithOwner();

        $request = Request::create('/');
        $request->setUserResolver(fn (): User => $user);

        $this->assertSame($ownTeam->slug, $this->sharedTeamSlug($request));
    }

    /**
     * Resolve the `currentTeam.slug` prop for the given request by evaluating the
     * shared closure, exactly as Inertia does when it builds the response.
     */
    private function sharedTeamSlug(Request $request): string
    {
        $props = app(HandleInertiaRequests::class)->share($request);

        $currentTeam = $props['currentTeam'] ?? null;

        $this->assertInstanceOf(Closure::class, $currentTeam);

        $shared = $currentTeam();

        $this->assertInstanceOf(UserTeam::class, $shared);

        return $shared->slug;
    }

    /**
     * @return array{0: Team, 1: User}
     */
    private function teamWithOwner(): array
    {
        $team = Team::factory()->create();

        $user = User::factory()->create();
        $team->members()->attach($user, ['role' => TeamRole::Owner->value]);
        $user->switchTeam($team);

        return [$team, $user];
    }
}
