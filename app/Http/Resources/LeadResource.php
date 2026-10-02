<?php

namespace App\Http\Resources;

use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Lead
 */
class LeadResource extends JsonResource
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
            'companyName' => $this->company_name,
            'contactName' => $this->contact_name,
            'phone' => $this->phone,
            'email' => $this->email,
            'source' => $this->source,
            'potentialProject' => $this->potential_project,
            'estimatedValue' => $this->estimated_value,
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
            'nextFollowUp' => $this->next_follow_up,
            'notes' => $this->notes,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
