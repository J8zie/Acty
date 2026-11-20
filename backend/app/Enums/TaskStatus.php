<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Accepted = 'accepted';
    case InProgress = 'in_progress';
    case Complete = 'complete';

    /** All the stored values, e.g. ['open', 'in_progress', ...]: used by validation rules and filters. */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
