<?php

namespace App\Enums;

// Whether an admin has checked a report yet.
enum ReviewStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    // Gets all the stored values as a list.
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
