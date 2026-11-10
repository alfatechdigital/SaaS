<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\WhatsAppMessageDirection;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One WhatsApp message, inbound or outbound (B-3, gelombang 1).
 *
 * Tenant-owned: the global scope from {@see BelongsToTeam} means every query is
 * already limited to the active tenant. Webhook code must therefore store rows
 * inside `TenantContext::runFor()`.
 *
 * @property int $id
 * @property int $team_id
 * @property WhatsAppMessageDirection $direction
 * @property string|null $wamid
 * @property string $from_number
 * @property string|null $to_number
 * @property string $type
 * @property string|null $body
 * @property string|null $status
 * @property array<string, mixed>|null $provider_payload
 * @property CarbonImmutable|null $received_at
 * @property CarbonImmutable|null $processed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'direction',
    'wamid',
    'from_number',
    'to_number',
    'type',
    'body',
    'status',
    'provider_payload',
    'received_at',
    'processed_at',
])]
class WhatsAppMessage extends Model
{
    use BelongsToTeam;

    /**
     * Eloquent would pluralise the class name to `whats_app_messages`.
     *
     * @var string
     */
    protected $table = 'whatsapp_messages';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => WhatsAppMessageDirection::class,
            'provider_payload' => 'array',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
