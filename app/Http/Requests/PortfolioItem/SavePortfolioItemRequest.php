<?php

namespace App\Http\Requests\PortfolioItem;

use App\Enums\TeamPermission;
use App\Http\Requests\TeamRequest;
use Illuminate\Contracts\Validation\ValidationRule;

class SavePortfolioItemRequest extends TeamRequest
{
    /**
     * Get the permission required to perform this request.
     */
    protected function permission(): TeamPermission
    {
        return TeamPermission::ManagePortfolio;
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
            'client' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'technologies' => ['present', 'array'],
            'technologies.*' => ['string', 'max:100'],
            'image_url' => ['nullable', 'string', 'max:2048'],
            'project_url' => ['nullable', 'string', 'max:2048'],
            'completion_date' => ['nullable', 'date'],
            'featured' => ['required', 'boolean'],
            'published' => ['required', 'boolean'],
        ];
    }
}
