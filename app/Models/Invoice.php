<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\BillingInterval;
use App\Enums\InvoiceStatus;
use Carbon\CarbonImmutable;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One bill for one subscription period (B-2, tugas 19 Nov).
 *
 * The invoice — not the payment provider — says which period a payment buys, so
 * it is the only place dunning can start from. One invoice per subscription per
 * period is enforced by a unique index, and a late payment voids the invoice it
 * supersedes instead of leaving two open bills for the same month.
 *
 * Tenant-owned through {@see BelongsToTeam}; queries outside a request or job must
 * run inside `TenantContext::runFor()`.
 *
 * @property int $id
 * @property int $team_id
 * @property int $subscription_id
 * @property string $number
 * @property InvoiceStatus $status
 * @property BillingInterval $billing_interval
 * @property int $amount
 * @property string $currency
 * @property CarbonImmutable $period_start
 * @property CarbonImmutable $period_end
 * @property CarbonImmutable $due_at
 * @property CarbonImmutable|null $paid_at
 * @property int $reminders_sent
 * @property CarbonImmutable|null $reminded_at
 * @property string|null $provider
 * @property string|null $provider_reference
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Team $team
 * @property-read Subscription $subscription
 */
#[Fillable([
    'team_id',
    'subscription_id',
    'number',
    'status',
    'billing_interval',
    'amount',
    'currency',
    'period_start',
    'period_end',
    'due_at',
    'paid_at',
    'reminders_sent',
    'reminded_at',
    'provider',
    'provider_reference',
])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use BelongsToTeam, HasFactory;

    /**
     * Eloquent would pluralise the class name to `invoices`, which is right;
     * declared anyway so a future rename of the class cannot silently repoint it.
     *
     * @var string
     */
    protected $table = 'invoices';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'billing_interval' => BillingInterval::class,
            'amount' => 'integer',
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
            'reminders_sent' => 'integer',
            'reminded_at' => 'datetime',
        ];
    }

    /**
     * The subscription this invoice belongs to.
     *
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Whether the invoice still expects a payment.
     */
    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    /**
     * Whether the invoice was settled.
     */
    public function isPaid(): bool
    {
        return $this->status === InvoiceStatus::Paid;
    }

    /**
     * Whether the due date has passed without a payment.
     */
    public function isOverdue(CarbonImmutable $now): bool
    {
        return $this->isOpen() && $this->due_at->lessThan($now);
    }

    /**
     * Invoices still waiting for a payment.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', InvoiceStatus::Open->value);
    }
}
