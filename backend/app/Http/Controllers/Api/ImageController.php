<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * GET /reports/{id}/private/{photo|selfie}: serves a private picture.
 *
 * The route has the `signed:relative` middleware: the address must carry a valid signature that has not expired
 * (15 minutes), and only admins are ever given such an address. Without it: 403.
 */
class ImageController extends Controller
{
    public function private(Report $report, string $kind): StreamedResponse
    {
        $path = match ($kind) {
            'selfie' => $report->guestReporter?->selfie_path,
            'photo' => $report->image_disk === 'private' ? $report->image_path : null,
            default => null,
        };

        $disk = Storage::disk('private');

        if (! $path || ! $disk->exists($path)) {
            throw $this->error('We could not find that.', 404);
        }

        return $disk->response($path, null, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
