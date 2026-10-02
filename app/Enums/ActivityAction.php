<?php

namespace App\Enums;

/**
 * The kind of mutation an activity log entry recorded.
 *
 * `activity_logs.action` stores a human-readable sentence (e.g.
 * "Menambahkan proyek"), which is what the UI shows. The UI also needs to know
 * *which kind* of change happened so it can colour the timeline, and deriving
 * that by string-matching in the frontend would be fragile.
 *
 * This enum is the single source of truth for both directions: the observer
 * writes the sentence from it, and `ActivityLogResource` reads the kind back.
 */
enum ActivityAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Deleted = 'deleted';

    /**
     * Verb that opens the stored action sentence, e.g. "Menambahkan proyek".
     */
    public function verb(): string
    {
        return match ($this) {
            self::Created => 'Menambahkan',
            self::Updated => 'Memperbarui',
            self::Deleted => 'Menghapus',
        };
    }

    /**
     * Past-tense word used in the stored detail sentence, e.g. "Proyek dihapus: X".
     */
    public function state(): string
    {
        return match ($this) {
            self::Created => 'ditambahkan',
            self::Updated => 'diperbarui',
            self::Deleted => 'dihapus',
        };
    }

    /**
     * Recover the action from the stored sentence's leading verb.
     */
    public static function fromActionString(string $action): ?self
    {
        foreach (self::cases() as $case) {
            if (str_starts_with($action, $case->verb())) {
                return $case;
            }
        }

        return null;
    }
}
