<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\ActivityEntityType;
use Database\Factories\ActivityLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property string $action
 * @property ActivityEntityType $entity_type
 * @property string|null $entity_id
 * @property string|null $details
 * @property int|null $performed_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read User|null $performedBy
 */
#[Fillable([
    'team_id',
    'action',
    'entity_type',
    'entity_id',
    'details',
    'performed_by_id',
])]
class ActivityLog extends Model
{
    /** @use HasFactory<ActivityLogFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entity_type' => ActivityEntityType::class,
        ];
    }

    /**
     * Get the user who performed the logged action.
     *
     * @return BelongsTo<User, $this>
     */
    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_id');
    }
}
