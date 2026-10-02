<?php

namespace App\Http\Requests\ContentItem;

use App\Enums\ContentPlatform;
use App\Enums\ContentStatus;
use App\Enums\TeamPermission;
use App\Http\Requests\TeamRequest;
use App\Rules\TeamMember;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class SaveContentItemRequest extends TeamRequest
{
    /**
     * Get the permission required to perform this request.
     */
    protected function permission(): TeamPermission
    {
        return TeamPermission::ManageContent;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:10000'],
            'platform' => ['required', Rule::enum(ContentPlatform::class)],
            'content_type' => ['nullable', 'string', 'max:255'],
            'media_url' => ['nullable', 'string', 'max:2048'],
            'status' => ['required', Rule::enum(ContentStatus::class)],
            'scheduled_at' => ['nullable', 'string', 'max:255'],
            'assignee_id' => ['nullable', 'integer', new TeamMember($this->team())],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
