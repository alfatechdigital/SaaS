<?php

namespace App\Http\Requests\CompanyProfile;

use App\Enums\TeamPermission;
use App\Http\Requests\TeamRequest;
use Illuminate\Contracts\Validation\ValidationRule;

class UpdateCompanyProfileRequest extends TeamRequest
{
    /**
     * Get the permission required to perform this request.
     */
    protected function permission(): TeamPermission
    {
        return TeamPermission::ManageCompanyProfile;
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
            'description' => ['nullable', 'string', 'max:1000'],
            'about' => ['nullable', 'string', 'max:10000'],
            'services' => ['present', 'array'],
            'services.*.id' => ['required', 'string', 'max:100'],
            'services.*.name' => ['required', 'string', 'max:255'],
            'services.*.desc' => ['nullable', 'string', 'max:1000'],
            'services.*.icon' => ['nullable', 'string', 'max:100'],
            'products' => ['present', 'array'],
            'products.*.id' => ['required', 'string', 'max:100'],
            'products.*.name' => ['required', 'string', 'max:255'],
            'products.*.desc' => ['nullable', 'string', 'max:1000'],
            'contact' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'social_links' => ['present', 'array'],
            'social_links.website' => ['nullable', 'string', 'max:255'],
            'social_links.instagram' => ['nullable', 'string', 'max:255'],
            'social_links.linkedin' => ['nullable', 'string', 'max:255'],
            'social_links.github' => ['nullable', 'string', 'max:255'],
            'faq' => ['present', 'array'],
            'faq.*.question' => ['required', 'string', 'max:255'],
            'faq.*.answer' => ['required', 'string', 'max:5000'],
        ];
    }
}
