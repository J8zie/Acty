<?php

namespace App\Enums;

// How far a volunteer has got with a task.
enum TaskStatus: string
{
    case Accepted = 'accepted';
    case InProgress = 'in_progress';
    case Complete = 'complete';

    // Gets all the stored values as a list.
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
