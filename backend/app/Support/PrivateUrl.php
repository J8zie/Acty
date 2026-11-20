<?php

namespace App\Support;

use Illuminate\Support\Facades\URL;

/**
 * A short-lived, signed link to a PRIVATE picture (a guest's selfie, or the photo of a guest report that has not been
 * approved yet). The <img> tag in the admin page cannot send the login token, so the link itself carries a signature
 * and an expiry time. Only admins are ever given such a link (ReportResource / ReporterResource).
 */
final class PrivateUrl
{
    public static function for(int $reportId, string $kind): string
    {
        $relative = URL::temporarySignedRoute(
            'reports.private',
            now()->addMinutes((int) config('actify.private_link_minutes')),
            ['report' => $reportId, 'kind' => $kind],
            absolute: false,
        );

        // Same address style as Storage::url(): built from APP_URL, so it works from any website address.
        return rtrim((string) config('app.url'), '/').$relative;
    }
}
