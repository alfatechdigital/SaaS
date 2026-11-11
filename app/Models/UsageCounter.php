<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One metric's usage during one billing period (B-3 tugas 11 Nov, B-2 nanti).
 *
 * Tenant-owned through {@see BelongsToTeam}; code that runs outside a request must
 * wrap itself in `TenantContext::runFor()`.
 *
 * @property int $id
 * @property int $team_id
 * @property string $metric
 * @property string $period
 * @property int $used
 * @property int $limit
 */
#[Fillable(['metric', 'period', 'used', 'limit'])]
class UsageCounter extends Model
{
    use BelongsToTeam;

    /**
     * Eloquent would pluralise the class name to `usage_counters`, which is right;
     * declared anyway so a future rename of the class cannot silently repoint it.
     *
     * @var string
     */
    protected $table = 'usage_counters';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'used' => 'integer',
            'limit' => 'integer',
        ];
    }
}
