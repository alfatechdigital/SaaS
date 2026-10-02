<?php

namespace App\Enums;

/**
 * Workflow status of a task.
 */
enum TaskStatus: string
{
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Review = 'review';
    case Done = 'done';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Todo => 'To Do',
            self::InProgress => 'Dikerjakan',
            self::Review => 'Review',
            self::Done => 'Selesai',
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
