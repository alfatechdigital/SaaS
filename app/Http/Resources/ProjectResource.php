<?php

namespace App\Http\Resources;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Project
 */
class ProjectResource extends JsonResource
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
            'leadId' => $this->lead_id,
            'name' => $this->name,
            'clientName' => $this->client_name,
            'description' => $this->description,
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
            'progress' => $this->progress,
            'startDate' => $this->start_date?->toDateString(),
            'deadline' => $this->deadline?->toDateString(),
            'projectValue' => $this->project_value,
            'picId' => $this->pic_id,
            'picName' => $this->whenLoaded('pic', fn () => $this->pic?->name),
            'picRole' => $this->whenLoaded('pic', fn () => $this->pic?->job_title),
            'technologies' => $this->technologies ?? [],
            'notes' => $this->notes,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
