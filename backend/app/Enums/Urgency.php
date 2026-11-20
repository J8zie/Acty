<?php

namespace App\Enums;

// How urgent a report is.
enum Urgency: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    // Gets all the stored values as a list.
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
