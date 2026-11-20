<?php

namespace App\Enums;

enum AssistanceType: string
{
    case Cleanup = 'cleanup';
    case Repair = 'repair';
    case Supplies = 'supplies';
    case Manpower = 'manpower';
    case Awareness = 'awareness';
    case Other = 'other';

    /** All the stored values, e.g. ['open', 'in_progress', ...]: used by validation rules and filters. */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
