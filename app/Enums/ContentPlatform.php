<?php

namespace App\Enums;

/**
 * Social media platform a content item is published to.
 */
enum ContentPlatform: string
{
    case Instagram = 'instagram';
    case Facebook = 'facebook';
    case Tiktok = 'tiktok';
    case Linkedin = 'linkedin';

    /**
     * Get the display label for the platform.
     */
    public function label(): string
    {
        return match ($this) {
            self::Instagram => 'Instagram',
            self::Facebook => 'Facebook',
            self::Tiktok => 'TikTok',
            self::Linkedin => 'LinkedIn',
        };
    }

    /**
     * Get all the platform values.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
