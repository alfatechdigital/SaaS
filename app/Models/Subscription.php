<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\BillingInterval;
use App\Enums\SubscriptionStatus;
use Carbon\CarbonImmutable;
use Database\Factories\SubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One tenant's subscription — the source of truth for what the tenant has bought.
 *
 * A subscription is *running* while `ended_at` is null, and the database allows
 * only one such row per tenant (see the migration's `current_team_id`). Status
 * alone cannot say that: a subscription canceled to end at period close keeps
 * `ended_at` null until the paid period is over (B.4.3).
 *
 * Rows are never deleted when a subscription ends: invoices and price history
 * point at them, and §B.4.3 keeps tenant data for 30 days before deletion.
 *
 * Tenant-owned through {@see BelongsToTeam}; code that runs outside a request must
 * wrap itself in `TenantContext::runFor()`.
 *
 * @property int $id
 * @property int $team_id
 * @property string $plan_code
 * @property BillingInterval|null $billing_interval
 * @property SubscriptionStatus $status
 * @property CarbonImmutable|null $trial_ends_at
 * @property CarbonImmutable $current_period_start
 * @property CarbonImmutable $current_period_end
 * @property CarbonImmutable|null $canceled_at
 * @property string $provider
 * @property string|null $provider_reference
 * @property CarbonImmutable|null $ended_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Team $team
 * @property-read Plan|null $plan
 */
#[Fillable([
    'plan_code',
    'billing_interval',
    'status',
    'trial_ends_at',
    'current_period_start',
    'current_period_end',
    'canceled_at',
    'provider',
    'provider_reference',
    'ended_at',
])]
#[Hidden(['current_team_id'])]
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * Eloquent would pluralise the class name to `subscriptions`, which is right;
     * declared anyway so a future rename of the class cannot silently repoint it.
     *
     * @var string
     */
    protected $table = 'subscriptions';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'billing_interval' => BillingInterval::class,
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'canceled_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /**
     * The commercial plan row behind `plan_code`, if it has been seeded.
     *
     * Null is a legitimate answer — a fresh deployment may not have run
     * `PlanSeeder` yet, and entitlement never depends on this relation.
     *
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_code', 'code');
    }

    /**
     * Whether this is still the tenant's subscription, i.e. not replaced yet.
     */
    public function isCurrent(): bool
    {
        return $this->ended_at === null;
    }

    /**
     * Whether this subscription is in its trial period.
     */
    public function onTrial(): bool
    {
        return $this->status === SubscriptionStatus::Trialing;
    }

    /**
     * Whether the tenant may use its features while in this status.
     */
    public function grantsAccess(): bool
    {
        return $this->status->grantsAccess();
    }

    /**
     * Whether the state machine permits moving to the given status.
     *
     * The transition itself (periods, `canceled_at`, `ended_at`) belongs to the
     * subscription service (tugas 18 Nov); the rules live in the enum so there is
     * exactly one place to change them.
     */
    public function canTransitionTo(SubscriptionStatus $status): bool
    {
        return $this->status->canTransitionTo($status);
    }

    /**
     * Running subscriptions only, regardless of status.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNull($this->getTable().'.ended_at');
    }
}
