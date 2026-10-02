<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * Registration is invitation-only (PDR-06).
 *
 * A user account is therefore only ever created as part of accepting a tenant
 * invitation — so a registrant never receives a tenant of their own. This is
 * what closed the "every signup becomes a tenant" hole (M-4).
 */
class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'invitation' => ['required', 'string'],
        ])->validate();

        $invitation = $this->pendingInvitation($input['invitation']);

        if (Str::lower($invitation->email) !== Str::lower($input['email'])) {
            throw ValidationException::withMessages([
                'email' => __('This invitation was sent to a different email address.'),
            ]);
        }

        return DB::transaction(function () use ($input, $invitation) {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            $invitation->team->memberships()->create([
                'user_id' => $user->id,
                'role' => $invitation->role->value,
            ]);

            $invitation->update(['accepted_at' => now()]);

            $user->switchTeam($invitation->team);

            return $user;
        });
    }

    /**
     * Resolve the pending invitation the registration is being completed with.
     */
    private function pendingInvitation(string $code): TeamInvitation
    {
        $invitation = TeamInvitation::query()
            ->with('team')
            ->where('code', $code)
            ->whereNull('accepted_at')
            ->where(fn ($query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>=', now()))
            ->first();

        if (! $invitation) {
            throw ValidationException::withMessages([
                'invitation' => __('This invitation is no longer valid.'),
            ]);
        }

        return $invitation;
    }
}
