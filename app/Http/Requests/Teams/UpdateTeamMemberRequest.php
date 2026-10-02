<?php

namespace App\Http\Requests\Teams;

use App\Enums\TeamRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTeamMemberRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * All rules are `sometimes` so the same endpoint can change a role, edit a
     * member's profile, or both — the controller only writes the keys present.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'role' => ['sometimes', 'required', 'string', Rule::in(array_column(TeamRole::assignable(), 'value'))],
            'job_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'skills' => ['sometimes', 'array'],
            'skills.*' => ['string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
