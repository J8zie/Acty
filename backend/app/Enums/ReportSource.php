<?php

namespace App\Enums;

enum ReportSource: string
{
    case Account = 'account';
    case Guest = 'guest';

    /** All the stored values, e.g. ['open', 'in_progress', ...]: used by validation rules and filters. */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
