<?php

namespace App\Enums;

// What kind of user someone is.
enum Role: string
{
    case Resident = 'resident';
    case Volunteer = 'volunteer';
    case Admin = 'admin';

    // Gets all the stored values as a list.
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
