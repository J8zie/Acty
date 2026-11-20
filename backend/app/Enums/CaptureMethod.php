<?php

namespace App\Enums;

enum CaptureMethod: string
{
    case Live = 'live';
    case CameraApp = 'camera-app';

    /** All the stored values, e.g. ['open', 'in_progress', ...]: used by validation rules and filters. */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
