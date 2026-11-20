<?php

namespace App\Enums;

// The kinds of help a report can ask for.
enum AssistanceType: string
{
    case Cleanup = 'cleanup';
    case Repair = 'repair';
    case Supplies = 'supplies';
    case Manpower = 'manpower';
    case Awareness = 'awareness';
    case Other = 'other';

    // Gets all the stored values as a list.
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
