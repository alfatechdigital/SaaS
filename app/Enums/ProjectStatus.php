<?php

namespace App\Enums;

/**
 * Lifecycle status of a project.
 */
enum ProjectStatus: string
{
    case Lead = 'lead';
    case Negotiation = 'negotiation';
    case Deal = 'deal';
    case Development = 'development';
    case Review = 'review';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Lead => 'Lead',
            self::Negotiation => 'Negosiasi',
            self::Deal => 'Deal',
            self::Development => 'Pengembangan',
            self::Review => 'Review',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
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
