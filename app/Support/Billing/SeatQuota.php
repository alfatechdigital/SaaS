<?php

namespace App\Support\Billing;

use App\Models\Team;
use App\Models\TeamInvitation;
use App\Support\Entitlements;

/**
 * How many people a tenant may have, and how many seats are spoken for (B-2, tugas 25 Nov).
 *
 * The limit is the plan's `users` number: 1 / 3 / 10 for Basic / Plus / Pro. It is
 * enforced when a seat is *claimed* rather than when the plan is read, because
 * the two ways to claim one — sending an invitation and accepting it — arrive
 * through different controllers.
 *
 * Seats in use are **counted, never tracked**: memberships plus pending
 * invitations. The design note in phase-04 §4.5.5 says the same thing
 * (`users` dihitung dari `team_members`, tidak di-increment manual), and it is
 * the only version that cannot drift when an invitation is cancelled, expires
 * or is declined. `usage_counters` therefore stays reserved for metrics that are
 * genuinely incremented per use (AI generations).
 */
final class SeatQuota
{
    /**
     * Seats the plan allows. A plan without the metric allows one person: the
     * owner, which is the only seat that can never be refused.
     */
    public function limit(Team $team): int
    {
        return max(1, app(Entitlements::class)->limit($team, 'users') ?? 1);
    }

    /**
     * Seats already claimed: members plus invitations that are still live.
     *
     * An invitation that is *about to be accepted* holds the seat it is claiming,
     * so the caller passes it as `$except` to avoid counting it twice.
     */
    public function used(Team $team, ?TeamInvitation $except = null): int
    {
        return $this->members($team) + $this->pendingInvitations($team, $except);
    }

    public function remaining(Team $team, ?TeamInvitation $except = null): int
    {
        return max(0, $this->limit($team) - $this->used($team, $except));
    }

    public function hasRoom(Team $team, ?TeamInvitation $except = null): bool
    {
        return $this->remaining($team, $except) > 0;
    }

    /**
     * Refuse the next seat when the plan has none left.
     *
     * Answering 403 — not a validation error — because the payload is fine and
     * the plan is not: the same request succeeds on a bigger plan.
     */
    public function ensureRoom(Team $team, ?TeamInvitation $except = null): void
    {
        abort_if(
            ! $this->hasRoom($team, $except),
            403,
            __('Kuota pengguna paket ini sudah penuh (:limit orang).', ['limit' => $this->limit($team)]),
        );
    }

    private function members(Team $team): int
    {
        return $team->memberships()->count();
    }

    /**
     * Invitations that could still be accepted.
     *
     * An expired row is kept until the daily prune runs, so counting it would
     * report a full team while nobody is actually coming.
     */
    private function pendingInvitations(Team $team, ?TeamInvitation $except = null): int
    {
        return $team->invitations()
            ->whereNull('accepted_at')
            ->when($except !== null, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->count();
    }
}
