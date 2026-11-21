<?php

namespace App\Http\Requests\WhatsApp;

use App\Enums\TeamPermission;
use App\Http\Requests\TeamRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Validates the tenant's WhatsApp Business credentials.
 *
 * The access token is optional when a token already exists: leaving the field
 * blank means "keep the current token", so re-saving the number does not require
 * pasting the secret again (and does not put it back on screen).
 */
class SaveWhatsAppSettingsRequest extends TeamRequest
{
    /**
     * Get the permission required to perform this request.
     */
    protected function permission(): TeamPermission
    {
        return TeamPermission::UpdateTeam;
    }

    /**
     * Normalise the values before validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone_number_id' => $this->trimmed('phone_number_id'),
            'business_account_id' => $this->trimmed('business_account_id'),
            'access_token' => $this->trimmed('access_token'),
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
            'phone_number_id' => ['required', 'string', 'max:64', 'regex:/^\d+$/'],
            'business_account_id' => ['nullable', 'string', 'max:64', 'regex:/^\d+$/'],
            'access_token' => [
                Rule::requiredIf(fn (): bool => ! $this->team()->isWhatsAppConnected()),
                'nullable',
                'string',
                'min:20',
                'max:4096',
            ],
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
