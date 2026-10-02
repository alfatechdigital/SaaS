<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Database\Factories\PortfolioItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property string $title
 * @property string|null $client
 * @property string|null $category
 * @property string|null $description
 * @property array<int, string>|null $technologies
 * @property string|null $image_url
 * @property string|null $project_url
 * @property Carbon|null $completion_date
 * @property bool $featured
 * @property bool $published
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 */
#[Fillable([
    'team_id',
    'title',
    'client',
    'category',
    'description',
    'technologies',
    'image_url',
    'project_url',
    'completion_date',
    'featured',
    'published',
])]
class PortfolioItem extends Model
{
    /** @use HasFactory<PortfolioItemFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'technologies' => 'array',
            'completion_date' => 'date',
            'featured' => 'boolean',
            'published' => 'boolean',
        ];
    }
}
