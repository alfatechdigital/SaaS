<?php

namespace App\Models;

use App\Concerns\BelongsToTeam;
use App\Enums\WhatsAppConversationState;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The bot's memory of one WhatsApp conversation (B-3, tugas 10 Nov).
 *
 * Keyed by (team, contact number): a webhook carries no session, so this row is
 * where "already asked for the name" lives between two messages.
 *
 * @property int $id
 * @property int $team_id
 * @property string $contact_number
 * @property WhatsAppConversationState $state
 * @property string|null $contact_name
 * @property string|null $company_name
 * @property int|null $lead_id
 * @property CarbonImmutable|null $last_inbound_at
 * @property CarbonImmutable|null $last_outbound_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Lead|null $lead
 */
#[Fillable([
    'contact_number',
    'state',
    'contact_name',
    'company_name',
    'lead_id',
    'last_inbound_at',
    'last_outbound_at',
])]
class WhatsAppConversation extends Model
{
    use BelongsToTeam;

    /**
     * Eloquent would pluralise the class name to `whats_app_conversations`.
     *
     * @var string
     */
    protected $table = 'whatsapp_conversations';

    /**
     * The lead this conversation produced, if any.
     *
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state' => WhatsAppConversationState::class,
            'last_inbound_at' => 'datetime',
            'last_outbound_at' => 'datetime',
        ];
    }
}
