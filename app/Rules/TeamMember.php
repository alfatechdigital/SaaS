<?php

namespace App\Rules;

use App\Models\Team;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Ensures the given user id belongs to the team being managed.
 */
class TeamMember implements ValidationRule
{
    public function __construct(private readonly Team $team)
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
        if ($value === null || $value === '') {
            return;
        }

        $isMember = $this->team->members()
            ->whereKey($value)
            ->exists();

        if (! $isMember) {
            $fail(__('Pengguna yang dipilih bukan anggota tim ini.'));
        }
    }
}
