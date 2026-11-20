<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

// Sends private report pictures to people who have a signed link.
class ImageController extends Controller
{
    // Returns a private photo or selfie for a report.
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
