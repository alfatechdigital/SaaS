<?php

namespace App\Http\Middleware;

use App\Models\Team;
use App\Support\Entitlements;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            // Read from `TenantContext` instead of `$user->currentTeam`, so the
            // frontend and everything else agree on the tenant of the current
            // request. Falling back to the user's own team keeps routes that
            // never resolve a tenant behaving exactly as before.
            //
            // @see \docs\implementation\phase-02-tenant-context.md tugas 2.1.5
            'currentTeam' => fn () => ($team = $this->activeTeam($request)) === null
                ? null
                : $user?->toUserTeam($team),
            'teamPermissions' => fn () => ($team = $this->activeTeam($request)) === null
                ? null
                : $user?->toTeamPermissions($team),
            // Entitlement (what the plan allows) is deliberately separate from
            // permission (who the user is). The UI needs both to hide what the
            // server would reject; the server remains the authority (Fase 4, 4.4).
            //
            // Shared only to authenticated users: a public page has no menus to
            // hide, and a tenant's plan is not a visitor's business.
            'features' => fn (): array => $user === null || ($team = $this->activeTeam($request)) === null
                ? []
                : $team->features(),
            'limits' => fn (): array => $user === null || ($team = $this->activeTeam($request)) === null
                ? []
                : app(Entitlements::class)->plan($team)['limits'],
        ];
    }

    /**
     * The tenant the current request runs for, if any.
     */
    protected function activeTeam(Request $request): ?Team
    {
        return app(TenantContext::class)->team() ?? $request->user()?->currentTeam;
    }
}
