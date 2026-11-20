<?php

namespace App\Enums;

enum Role: string
{
    case Resident = 'resident';
    case Volunteer = 'volunteer';
    case Admin = 'admin';

    /** All the stored values, e.g. ['open', 'in_progress', ...]: used by validation rules and filters. */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
