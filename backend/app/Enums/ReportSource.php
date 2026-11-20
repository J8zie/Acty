<?php

namespace App\Enums;

enum ReportSource: string
{
    case Account = 'account';
    case Guest = 'guest';


    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
