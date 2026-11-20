<?php

namespace App\Enums;

enum ReportStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Cancelled = 'cancelled';


    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
