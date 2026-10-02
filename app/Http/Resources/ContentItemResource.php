<?php

namespace App\Http\Resources;

use App\Models\ContentItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ContentItem
 */
class ContentItemResource extends JsonResource
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
            'title' => $this->title,
            'caption' => $this->caption,
            'platform' => $this->platform->value,
            'platformLabel' => $this->platform->label(),
            'contentType' => $this->content_type,
            'mediaUrl' => $this->media_url,
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
            'scheduledAt' => $this->scheduled_at,
            'assigneeId' => $this->assignee_id,
            'assigneeName' => $this->whenLoaded('assignee', fn () => $this->assignee?->name),
            'notes' => $this->notes,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
