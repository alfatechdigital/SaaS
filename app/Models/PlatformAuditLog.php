<?php

namespace App\Models;

use App\Enums\PlatformAuditAction;
use Database\Factories\PlatformAuditLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Records what a platform operator did across tenants.
 *
 * Deliberately **not** tenant-scoped: the operator works outside any single
 * tenant, and the row must survive the deletion of the tenant it refers to.
 * That is also why it is kept apart from the tenant-owned `activity_logs` — the
 * two answer different questions (tugas 2.4.3).
 *
 * @property int $id
 * @property int|null $actor_id
 * @property PlatformAuditAction $action
 * @property string $target_type
 * @property string|null $target_id
 * @property array<string, mixed>|null $details
 * @property string|null $ip_address
 * @property Carbon|null $created_at
 * @property-read User|null $actor
 * @property-read Model|null $target
 */
#[Fillable([
    'actor_id',
    'action',
    'target_type',
    'target_id',
    'details',
    'ip_address',
])]
class PlatformAuditLog extends Model
{
    /** @use HasFactory<PlatformAuditLogFactory> */
    use HasFactory;

    /**
     * Audit rows are append-only, so there is no `updated_at` to maintain.
     */
    public const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => PlatformAuditAction::class,
            'details' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Get the operator who performed the action.
     *
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Get the record the action was performed on.
     *
     * @return MorphTo<Model, $this>
     */
    public function target(): MorphTo
    {
        return $this->morphTo();
    }
}
