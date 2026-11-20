<?php

namespace App\Support;

use Illuminate\Support\Facades\URL;

// Makes short-lived signed links to private report pictures.
final class PrivateUrl
{
    // Builds a signed link to a report's private photo or selfie.
    public static function for(int $reportId, string $kind): string
    {
        return URL::temporarySignedRoute(
            'reports.private',
            now()->addMinutes((int) config('actify.private_link_minutes')),
            ['report' => $reportId, 'kind' => $kind],
            absolute: false,
        );
    }
}
