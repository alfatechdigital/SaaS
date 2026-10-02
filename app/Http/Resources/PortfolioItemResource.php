<?php

namespace App\Http\Resources;

use App\Models\PortfolioItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PortfolioItem
 */
class PortfolioItemResource extends JsonResource
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
            'client' => $this->client,
            'category' => $this->category,
            'description' => $this->description,
            'technologies' => $this->technologies ?? [],
            'imageUrl' => $this->image_url,
            'projectUrl' => $this->project_url,
            'completionDate' => $this->completion_date?->toDateString(),
            'featured' => $this->featured,
            'published' => $this->published,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
