<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One verified payment notification (B-2, tugas 24 Nov).
 *
 * Kept for two reasons: the idempotency key (provider + order + transaction + raw
 * status), and an audit trail of what the provider told us — money questions cannot
 * be answered from logs alone.
 *
 * Not tenant-owned on purpose (see the migration): an unknown order id still has to
 * be recorded instead of rejected, otherwise retries become an attack surface.
 *
 * @property int $id
 * @property string $provider
 * @property string $order_id
 * @property string|null $transaction_id
 * @property string $transaction_status
 * @property array<string, mixed> $payload
 * @property CarbonImmutable|null $processed_at
 * @property string|null $error
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'provider',
    'order_id',
    'transaction_id',
    'transaction_status',
    'payload',
    'processed_at',
    'error',
])]
class PaymentEvent extends Model
{
    /**
     * Eloquent would pluralise the class name to `payment_events`, which is right;
     * declared anyway so a future rename of the class cannot silently repoint it.
     *
     * @var string
     */
    protected $table = 'payment_events';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * Whether this delivery already reached a final decision.
     */
    public function isProcessed(): bool
    {
        return $this->processed_at !== null;
    }
}
