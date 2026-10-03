<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\ProjectStatus;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int|null $lead_id
 * @property string $name
 * @property string|null $client_name
 * @property string|null $description
 * @property ProjectStatus $status
 * @property int $progress
 * @property Carbon|null $start_date
 * @property Carbon|null $deadline
 * @property int $project_value
 * @property int|null $pic_id
 * @property array<int, string>|null $technologies
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read User|null $pic
 * @property-read Collection<int, Task> $tasks
 * @property-read Collection<int, Transaction> $transactions
 */
#[Fillable([
    'team_id',
    'lead_id',
    'name',
    'client_name',
    'description',
    'status',
    'progress',
    'start_date',
    'deadline',
    'project_value',
    'pic_id',
    'technologies',
    'notes',
])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'progress' => 'integer',
            'start_date' => 'date',
            'deadline' => 'date',
            'project_value' => 'integer',
            'technologies' => 'array',
        ];
    }

    /**
     * Get the user in charge of this project.
     *
     * @return BelongsTo<User, $this>
     */
    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_id');
    }

    /**
     * Get the lead this project was converted from, if any.
     *
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Get the tasks belonging to this project.
     *
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Get the transactions belonging to this project.
     *
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
