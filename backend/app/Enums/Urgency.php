<?php

namespace App\Enums;

enum Urgency: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    /** All the stored values, e.g. ['open', 'in_progress', ...]: used by validation rules and filters. */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
