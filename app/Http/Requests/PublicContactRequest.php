<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for the public contact form (F-7, 16 Nov).
 *
 * Three general fields replace the old software-house consultation form
 * (`service_type`, `budget_estimate`). Those belong to the Plus `form_builder`
 * template, not to the contact form every plan receives.
 *
 * Deliberately does **not** extend `TeamRequest`: there is no authenticated user
 * and no `TeamPermission` to check. The team comes from the route binding, and
 * abuse is limited by the `throttle` middleware on the route.
 */
class PublicContactRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Trim the three fields, turning whitespace-only values into `null` so
     * `required` rejects them instead of accepting a blank inquiry.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->trimmed('name'),
            'contact' => $this->trimmed('contact'),
            'message' => $this->trimmed('message'),
        ]);
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
            'contact' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ];
    }

    /**
     * Read a field as a trimmed string, turning an empty value into `null`.
     */
    private function trimmed(string $key): ?string
    {
        $value = $this->input($key);

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
