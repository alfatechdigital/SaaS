<?php

namespace App\Enums;

/**
 * Which side of a WhatsApp conversation wrote a message.
 *
 * Stored as a string so the rows stay readable in a database console and in
 * exports, matching the other enums in this project.
 */
enum WhatsAppMessageDirection: string
{
    case Inbound = 'inbound';
    case Outbound = 'outbound';

    public function label(): string
    {
        return match ($this) {
            self::Inbound => 'Masuk',
            self::Outbound => 'Keluar',
        };
    }
}
