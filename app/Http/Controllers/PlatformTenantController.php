<?php

namespace App\Http\Controllers;

use App\Actions\Teams\CreateTeam;
use App\Http\Requests\Teams\SaveTeamRequest;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The platform layer's home: a cross-tenant view that lives outside the tenant
 * shell. Only platform admins may enter — see PDR-03, PDR-04 and PDR-05.
 *
 * This intentionally does NOT use the `{current_team}` route group: the operator
 * is not acting "inside" one tenant when administering the installation.
 */
class PlatformTenantController extends Controller
{
    /**
     * List every tenant in this installation.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('platform/tenants/Index', [
            'tenants' => Team::query()
                ->withCount('members')
                ->orderBy('name')
                ->get()
                ->map(fn (Team $team): array => [
                    'id' => $team->id,
                    'name' => $team->name,
                    'slug' => $team->slug,
                    'isPersonal' => $team->is_personal,
                    'publicPageEnabled' => $team->public_page_enabled,
                    'membersCount' => $team->members_count ?? 0,
                    'createdAt' => $team->created_at?->toISOString(),
                ]),
        ]);
    }

    /**
     * Create a new tenant on behalf of the platform.
     */
    public function store(SaveTeamRequest $request, CreateTeam $createTeam): RedirectResponse
    {
        // `switchToTeam: false` — creating a tenant must not hijack the
        // operator's own active team.
        $team = $createTeam->handle(
            $request->user(),
            $request->validated('name'),
            switchToTeam: false,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Tenant ":name" created.', ['name' => $team->name]),
        ]);

        return to_route('platform.tenants.index');
    }

    /**
     * Take a tenant's public page offline, or bring it back (PDR-12).
     *
     * Deactivating only hides the page; the tenant's data is untouched.
     */
    public function updatePublicPage(Request $request, Team $team): RedirectResponse
    {
        $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $enabled = $request->boolean('enabled');

        $team->update(['public_page_enabled' => $enabled]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $enabled
                ? __('Halaman publik ":name" aktif kembali.', ['name' => $team->name])
                : __('Halaman publik ":name" dinonaktifkan.', ['name' => $team->name]),
        ]);

        return to_route('platform.tenants.index');
    }

    /**
     * Delete a tenant from the installation.
     *
     * Deleting a tenant is platform governance, so it lives here instead of in
     * the tenant's own screens — which is where it used to be (PDR-03, PDR-07).
     */
    public function destroy(Team $team): RedirectResponse
    {
        abort_if($team->is_personal, 403, __('A personal team cannot be deleted.'));

        $name = $team->name;

        DB::transaction(function () use ($team): void {
            // Everyone whose active team was this tenant loses it. A user
            // created through an invitation has no personal team, so this may
            // legitimately end up null (PDR-06).
            User::query()
                ->where('current_team_id', $team->id)
                ->get()
                ->each(fn (User $member) => $member->update([
                    'current_team_id' => $member->personalTeam()?->id,
                ]));

            $team->invitations()->delete();
            $team->memberships()->delete();
            $team->delete();
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Tenant ":name" deleted.', ['name' => $name]),
        ]);

        return to_route('platform.tenants.index');
    }
}
