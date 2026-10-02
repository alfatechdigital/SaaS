<?php

namespace App\Http\Requests\Project;

use App\Enums\ProjectStatus;
use App\Enums\TeamPermission;
use App\Http\Requests\TeamRequest;
use App\Rules\TeamMember;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class SaveProjectRequest extends TeamRequest
{
    /**
     * Get the permission required to perform this request.
     */
    protected function permission(): TeamPermission
    {
        return TeamPermission::ManageProjects;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::enum(ProjectStatus::class)],
            'progress' => ['required', 'integer', 'min:0', 'max:100'],
            'start_date' => ['nullable', 'date'],
            'deadline' => ['nullable', 'date', 'after_or_equal:start_date'],
            'project_value' => ['required', 'integer', 'min:0'],
            'pic_id' => ['nullable', 'integer', new TeamMember($this->team())],
            'technologies' => ['present', 'array'],
            'technologies.*' => ['string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
