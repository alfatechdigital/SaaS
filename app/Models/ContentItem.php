<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\ContentPlatform;
use App\Enums\ContentStatus;
use Database\Factories\ContentItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property string $title
 * @property string|null $caption
 * @property ContentPlatform $platform
 * @property string|null $content_type
 * @property string|null $media_url
 * @property ContentStatus $status
 * @property string|null $scheduled_at
 * @property int|null $assignee_id
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read User|null $assignee
 */
#[Fillable([
    'team_id',
    'title',
    'caption',
    'platform',
    'content_type',
    'media_url',
    'status',
    'scheduled_at',
    'assignee_id',
    'notes',
])]
class ContentItem extends Model
{
    /** @use HasFactory<ContentItemFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'platform' => ContentPlatform::class,
            'status' => ContentStatus::class,
        ];
    }

    /**
     * Get the user assigned to produce this content.
     *
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }
}
