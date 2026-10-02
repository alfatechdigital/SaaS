<?php

namespace App\Http\Resources;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Transaction
 */
class TransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'typeLabel' => $this->type->label(),
            'category' => $this->category->value,
            'categoryLabel' => $this->category->label(),
            'projectId' => $this->project_id,
            'projectName' => $this->whenLoaded('project', fn () => $this->project?->name),
            'description' => $this->description,
            'amount' => $this->amount,
            'date' => $this->date->toDateString(),
            'createdById' => $this->created_by_id,
            'createdByName' => $this->whenLoaded('createdBy', fn () => $this->createdBy?->name),
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
