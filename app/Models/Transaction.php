<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\TransactionCategory;
use App\Enums\TransactionType;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property TransactionType $type
 * @property TransactionCategory $category
 * @property int|null $project_id
 * @property string $description
 * @property int $amount
 * @property Carbon $date
 * @property int|null $created_by_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read Project|null $project
 * @property-read User|null $createdBy
 */
#[Fillable([
    'team_id',
    'type',
    'category',
    'project_id',
    'description',
    'amount',
    'date',
    'created_by_id',
])]
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'category' => TransactionCategory::class,
            'amount' => 'integer',
            'date' => 'date',
        ];
    }

    /**
     * Get the project this transaction is attributed to.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the user who recorded this transaction.
     *
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
