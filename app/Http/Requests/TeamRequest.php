<?php

namespace App\Http\Requests;

use App\Enums\TeamPermission;
use App\Models\Team;
use App\Models\User;
use App\Support\CurrentTeam;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base request for every team-scoped domain resource.
 *
 * Authorization is centralised here so each resource only has to declare which
 * TeamPermission it requires. See docs/IMPLEMENTATION_PLAN.md ADR-09.
 */
abstract class TeamRequest extends FormRequest
{
    /**
     * Get the permission required to perform this request.
     */
    abstract protected function permission(): TeamPermission;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User) {
            return false;
        }

        $team = $this->team();

        return $user->belongsToTeam($team)
            && $user->hasTeamPermission($team, $this->permission());
    }

    /**
     * Get the team the request is scoped to.
     */
    public function team(): Team
    {
        return CurrentTeam::from($this);
    }

    /**
     * Get the authenticated user, aborting when there is none.
     */
    public function actor(): User
    {
        $user = $this->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }

    /**
     * Get the id of the team the request is scoped to.
     */
    protected function teamId(): int
    {
        return $this->team()->id;
    }
}
