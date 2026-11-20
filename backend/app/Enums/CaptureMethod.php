<?php

namespace App\Enums;

enum CaptureMethod: string
{
    case Live = 'live';
    case CameraApp = 'camera-app';


    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
