<?php

namespace App\Http\Resources;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Task
 */
class TaskResource extends JsonResource
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
            'projectId' => $this->project_id,
            'title' => $this->title,
            'description' => $this->description,
            'assigneeId' => $this->assignee_id,
            'assigneeName' => $this->whenLoaded('assignee', fn () => $this->assignee?->name),
            'priority' => $this->priority->value,
            'priorityLabel' => $this->priority->label(),
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
            'dueDate' => $this->due_date?->toDateString(),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
