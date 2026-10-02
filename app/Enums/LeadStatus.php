<?php

namespace App\Enums;

/**
 * Sales pipeline status of a lead.
 */
enum LeadStatus: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case FollowUp = 'follow_up';
    case Meeting = 'meeting';
    case Proposal = 'proposal';
    case Negotiation = 'negotiation';
    case Won = 'won';
    case Lost = 'lost';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::New => 'Baru',
            self::Contacted => 'Dihubungi',
            self::FollowUp => 'Follow Up',
            self::Meeting => 'Meeting',
            self::Proposal => 'Proposal',
            self::Negotiation => 'Negosiasi',
            self::Won => 'Menang',
            self::Lost => 'Gagal',
        };
    }

    /**
     * Get all the status values.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
