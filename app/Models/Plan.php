<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A sellable plan, mirrored from `config/plans.php` + `config/billing.php` (B-2).
 *
 * The row carries **commercial** metadata only — name, price, whether the plan is
 * still on sale. Features and quotas are not here: entitlement is read from config
 * (ADR-14, D-06), so a price change can never change what a tenant may use.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property int $price_monthly
 * @property int|null $price_yearly
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['code', 'name', 'description', 'price_monthly', 'price_yearly', 'is_active'])]
class Plan extends Model
{
    /**
     * Eloquent would pluralise the class name to `plans`, which is right;
     * declared anyway so a future rename of the class cannot silently repoint it.
     *
     * @var string
     */
    protected $table = 'plans';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_monthly' => 'integer',
            'price_yearly' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Plans currently on sale.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * The plan with the given code, or `null` when it is not in the table.
     */
    public static function findByCode(string $code): ?self
    {
        return static::query()->where('code', $code)->first();
    }
}
