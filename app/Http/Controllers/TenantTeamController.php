<?php

namespace App\Http\Controllers;

use App\Enums\TeamRole;
use App\Http\Requests\Teams\SaveTeamRequest;
use App\Http\Requests\Teams\StoreTeamInvitationRequest;
use App\Http\Requests\Teams\UpdateTeamMemberRequest;
use App\Models\Membership;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\Teams\TeamInvitation as TeamInvitationNotification;
use App\Support\CurrentTeam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manages the active tenant's own team: members, invitations and the team name.
 *
 * Replaces the starter kit's `/settings/teams` screens so the feature renders in
 * the tenant shell instead of throwing the user into a different layout —
 * see PDR-04 and PDR-07, and TBD-08 / M-5 in docs/IMPLEMENTATION_PLAN.md.
 */
class TenantTeamController extends Controller
{
    /**
     * Display the tenant's team management page.
     */
    public function index(Request $request): Response
    {
        $team = CurrentTeam::from($request);
        $user = $request->user();

        return Inertia::render('admin/team/Index', [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'slug' => $team->slug,
                'isPersonal' => $team->is_personal,
            ],
            'members' => $team->members()->get()->map(function (User $member): array {
                /** @var Membership $membership */
                $membership = $member->getRelation('pivot');

                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'avatar' => $member->avatar ?? null,
                    'role' => $membership->role->value,
                    'role_label' => $membership->role->label(),
                    'job_title' => $member->job_title,
                    'phone' => $member->phone,
                    'skills' => $member->skills ?? [],
                    'is_active' => $member->is_active,
                ];
            }),
            'invitations' => $team->invitations()
                ->whereNull('accepted_at')
                ->get()
                ->map(fn (TeamInvitation $invitation): array => [
                    'code' => $invitation->code,
                    'email' => $invitation->email,
                    'role' => $invitation->role->value,
                    'role_label' => $invitation->role->label(),
                    'created_at' => $invitation->created_at?->toISOString(),
                ]),
            'permissions' => $user->toTeamPermissions($team),
            'availableRoles' => TeamRole::assignable(),
        ]);
    }

    /**
     * Rename the tenant's team.
     */
    public function update(SaveTeamRequest $request): RedirectResponse
    {
        $team = CurrentTeam::from($request);

        Gate::authorize('update', $team);

        $team->update(['name' => $request->validated('name')]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Team updated.')]);

        return to_route('team.index', ['current_team' => $team->slug]);
    }

    /**
     * Update a member's role and/or profile.
     *
     * The role lives on the membership (per team), while the profile fields live
     * on the user record, so a profile edit is visible in every team that user
     * belongs to. Only the keys actually present in the request are written.
     */
    public function updateMember(UpdateTeamMemberRequest $request): RedirectResponse
    {
        $team = CurrentTeam::from($request);
        $user = $this->memberFromRoute($request);

        Gate::authorize('updateMember', $team);

        $data = $request->validated();

        $membership = $team->memberships()
            ->where('user_id', $user->id)
            ->firstOrFail();

        if (array_key_exists('role', $data)) {
            $membership->update(['role' => TeamRole::from($data['role'])]);
        }

        $profile = Arr::only($data, ['job_title', 'phone', 'skills', 'is_active']);

        if ($profile !== []) {
            $user->update($profile);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member updated.')]);

        return to_route('team.index', ['current_team' => $team->slug]);
    }

    /**
     * Remove a member from the tenant.
     */
    public function destroyMember(Request $request): RedirectResponse
    {
        $team = CurrentTeam::from($request);
        $user = $this->memberFromRoute($request);

        Gate::authorize('removeMember', $team);

        abort_if($team->owner()?->is($user), 403, __('The team owner cannot be removed.'));

        $team->memberships()
            ->where('user_id', $user->id)
            ->delete();

        if ($user->isCurrentTeam($team)) {
            // A user created through an invitation has no personal team, so
            // there may genuinely be nowhere to fall back to (PDR-06).
            $fallback = $user->personalTeam() ?? $user->fallbackTeam($team);

            $user->update(['current_team_id' => $fallback?->id]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Member removed.')]);

        return to_route('team.index', ['current_team' => $team->slug]);
    }

    /**
     * Invite someone to the tenant.
     */
    public function storeInvitation(StoreTeamInvitationRequest $request): RedirectResponse
    {
        $team = CurrentTeam::from($request);

        Gate::authorize('inviteMember', $team);

        $invitation = $team->invitations()->create([
            'email' => $request->validated('email'),
            'role' => TeamRole::from($request->validated('role')),
            'invited_by' => $request->user()->id,
            'expires_at' => now()->addDays(3),
        ]);

        Notification::route('mail', $invitation->email)
            ->notify(new TeamInvitationNotification($invitation));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation sent.')]);

        return to_route('team.index', ['current_team' => $team->slug]);
    }

    /**
     * Cancel a pending invitation.
     */
    public function destroyInvitation(Request $request): RedirectResponse
    {
        $team = CurrentTeam::from($request);
        $invitation = $this->invitationFromRoute($request);

        abort_unless($invitation->team_id === $team->id, 404);

        Gate::authorize('cancelInvitation', $team);

        $invitation->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation cancelled.')]);

        return to_route('team.index', ['current_team' => $team->slug]);
    }

    /**
     * Resolve the `{user}` route segment.
     *
     * `{current_team}` is not an implicit binding, so controller arguments get
     * filled positionally from the route parameters (IMPLEMENTATION_PLAN §12).
     * Reading the segment explicitly is the pattern the domain controllers use.
     */
    private function memberFromRoute(Request $request): User
    {
        $value = $request->route('user');

        return $value instanceof User ? $value : User::findOrFail((int) $value);
    }

    /**
     * Resolve the `{invitation}` route segment, which is keyed by code.
     */
    private function invitationFromRoute(Request $request): TeamInvitation
    {
        $value = $request->route('invitation');

        return $value instanceof TeamInvitation
            ? $value
            : TeamInvitation::query()->where('code', (string) $value)->firstOrFail();
    }
}
