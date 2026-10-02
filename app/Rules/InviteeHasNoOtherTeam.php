<?php

namespace App\Rules;

use App\Models\Team;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Enforces PDR-10: one user may belong to only one tenant.
 *
 * Checked when the invitation is created, so the inviter finds out immediately
 * instead of the invitee hitting a wall at acceptance time.
 */
class InviteeHasNoOtherTeam implements ValidationRule
{
    public function __construct(protected Team $team)
    {
        //
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $invitee = User::query()
            ->whereRaw('LOWER(email) = ?', [strtolower((string) $value)])
            ->first();

        if ($invitee?->belongsToAnotherTeam($this->team)) {
            $fail(__('This person already belongs to another team.'));
        }
    }
}
