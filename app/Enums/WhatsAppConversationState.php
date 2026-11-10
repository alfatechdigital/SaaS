<?php

namespace App\Enums;

/**
 * How far the WhatsApp bot has taken a conversation.
 *
 * The happy path is linear — `new` → `awaiting_name` → `awaiting_company` →
 * `awaiting_message` → `completed` — and each step exists because the next one
 * needs something only the customer can supply. `handed_over` is the escape hatch:
 * once a human takes over, the bot stays silent so a person is never talked over by
 * an automated reply.
 *
 * A `completed` conversation also stops the bot: the lead exists, and a second
 * greeting to someone who already introduced themselves reads like a broken robot.
 */
enum WhatsAppConversationState: string
{
    case New = 'new';
    case AwaitingName = 'awaiting_name';
    case AwaitingCompany = 'awaiting_company';
    case AwaitingMessage = 'awaiting_message';
    case Completed = 'completed';
    case HandedOver = 'handed_over';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Baru',
            self::AwaitingName => 'Menunggu nama',
            self::AwaitingCompany => 'Menunggu perusahaan',
            self::AwaitingMessage => 'Menunggu kebutuhan',
            self::Completed => 'Selesai',
            self::HandedOver => 'Dialihkan ke manusia',
        };
    }

    /**
     * Whether the bot may answer a message in this state.
     */
    public function answersAutomatically(): bool
    {
        return ! in_array($this, [self::Completed, self::HandedOver], true);
    }
}
