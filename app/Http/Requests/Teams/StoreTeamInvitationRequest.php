<?php

namespace App\Http\Requests\Teams;

use App\Enums\TeamRole;
use App\Rules\InviteeHasNoOtherTeam;
use App\Rules\UniqueTeamInvitation;
use App\Support\CurrentTeam;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Invitation created from inside the tenant shell, where the team comes from the
 * `{current_team}` URL segment rather than a `{team}` route parameter.
 */
class StoreTeamInvitationRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $team = CurrentTeam::from($this);

        return [
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                new UniqueTeamInvitation($team),
                new InviteeHasNoOtherTeam($team),
            ],
            'role' => ['required', 'string', Rule::in(array_column(TeamRole::assignable(), 'value'))],
        ];
    }
}
