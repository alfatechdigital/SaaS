<?php

namespace App\Http\Requests\Transaction;

use App\Enums\TeamPermission;
use App\Enums\TransactionCategory;
use App\Http\Requests\TeamRequest;
use App\Models\Project;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class SaveTransactionRequest extends TeamRequest
{
    /**
     * Get the permission required to perform this request.
     */
    protected function permission(): TeamPermission
    {
        return TeamPermission::ManageFinance;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $teamId = $this->teamId();

        return [
            'category' => ['required', Rule::enum(TransactionCategory::class)],
            'project_id' => [
                'nullable',
                'integer',
                Rule::exists(Project::class, 'id')->where('team_id', $teamId),
            ],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'integer', 'min:1'],
            'date' => ['required', 'date'],
        ];
    }
}
