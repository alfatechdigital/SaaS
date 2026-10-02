<?php

namespace App\Enums;

/**
 * The kind of entity an activity log entry refers to.
 */
enum ActivityEntityType: string
{
    case Project = 'project';
    case Lead = 'lead';
    case Content = 'content';
    case Transaction = 'transaction';
    case Task = 'task';
    case Portfolio = 'portfolio';
    case CompanyProfile = 'company_profile';
    case Team = 'team';

    /**
     * Get the display label for the entity type.
     */
    public function label(): string
    {
        return match ($this) {
            self::Project => 'Proyek',
            self::Lead => 'Lead',
            self::Content => 'Konten',
            self::Transaction => 'Transaksi',
            self::Task => 'Tugas',
            self::Portfolio => 'Portfolio',
            self::CompanyProfile => 'Profil Perusahaan',
            self::Team => 'Tim',
        };
    }

    /**
     * Get all the entity type values.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
