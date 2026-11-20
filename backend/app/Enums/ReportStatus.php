<?php

namespace App\Enums;

enum ReportStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Cancelled = 'cancelled';

    /** All the stored values, e.g. ['open', 'in_progress', ...]: used by validation rules and filters. */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
