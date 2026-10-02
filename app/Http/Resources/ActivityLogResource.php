<?php

namespace App\Http\Resources;

use App\Enums\ActivityAction;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ActivityLog
 */
class ActivityLogResource extends JsonResource
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
            'action' => $this->action,
            'actionType' => ActivityAction::fromActionString($this->action)?->value,
            'entityType' => $this->entity_type->value,
            'entityTypeLabel' => $this->entity_type->label(),
            'entityId' => $this->entity_id,
            'details' => $this->details,
            'performedById' => $this->performed_by_id,
            'performedByName' => $this->whenLoaded('performedBy', fn () => $this->performedBy?->name),
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
