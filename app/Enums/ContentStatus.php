<?php

namespace App\Enums;

/**
 * Editorial status of a social media content item.
 */
enum ContentStatus: string
{
    case Idea = 'idea';
    case Draft = 'draft';
    case Review = 'review';
    case Approved = 'approved';
    case Scheduled = 'scheduled';
    case Published = 'published';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Idea => 'Ide',
            self::Draft => 'Draft',
            self::Review => 'Review',
            self::Approved => 'Disetujui',
            self::Scheduled => 'Terjadwal',
            self::Published => 'Terbit',
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
