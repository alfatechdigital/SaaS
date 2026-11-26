<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Support\Entitlements;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One feature a tenant bought separately from its plan (PDR-06, B-2 tugas 26 Nov).
 *
 * Add-ons live beside the plan, never inside it: "Basic + 1 add-on" still shows as
 * Basic everywhere (phase-04 §4.5.6). {@see Entitlements} unions the
 * plan's features with these when it answers `can()`.
 *
 * Tenant-owned through {@see BelongsToTeam}; the platform panel reads and writes
 * it inside `TenantContext::runFor()`.
 *
 * @property int $id
 * @property int $team_id
 * @property string $feature
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable|null $ends_at
 * @property string|null $provider_reference
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['feature', 'starts_at', 'ends_at', 'provider_reference'])]
class TenantAddon extends Model
{
    use BelongsToTeam;

    /**
     * Eloquent would pluralise the class name to `tenant_addons`, which is right;
     * declared anyway so a future rename of the class cannot silently repoint it.
     *
     * @var string
     */
    protected $table = 'tenant_addons';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * Add-ons that grant their feature right now.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(function (Builder $query): void {
            $query->whereNull('ends_at')->orWhere('ends_at', '>', now());
        });
    }

    /**
     * Whether this row still grants its feature.
     */
    public function isActive(): bool
    {
        return $this->ends_at === null || $this->ends_at->isFuture();
    }
}
