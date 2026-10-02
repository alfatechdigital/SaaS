<?php

namespace App\Http\Requests\Lead;

use App\Enums\LeadStatus;
use App\Enums\TeamPermission;
use App\Http\Requests\TeamRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class SaveLeadRequest extends TeamRequest
{
    /**
     * Get the permission required to perform this request.
     */
    protected function permission(): TeamPermission
    {
        return TeamPermission::ManageLeads;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'source' => ['nullable', 'string', 'max:255'],
            'potential_project' => ['nullable', 'string', 'max:255'],
            'estimated_value' => ['required', 'integer', 'min:0'],
            'status' => ['required', Rule::enum(LeadStatus::class)],
            'next_follow_up' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
