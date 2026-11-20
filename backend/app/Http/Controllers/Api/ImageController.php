<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Report;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

// Sends private report pictures and videos to people who have a signed link.
class ImageController extends Controller
{
    // Returns a private photo, selfie or video for a report.
    public function private(Report $report, string $kind): StreamedResponse|BinaryFileResponse
    {
        if ($kind === 'video') {
            return $this->video($report);
        }

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

    // Streams a private report video with range support so it can be played.
    private function video(Report $report): BinaryFileResponse
    {
        $path = $report->video_disk === 'private' ? $report->video_path : null;
        $disk = Storage::disk('private');

        if (! $path || ! $disk->exists($path)) {
            throw $this->error('We could not find that.', 404);
        }

        $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'webm' => 'video/webm',
            'mov' => 'video/quicktime',
            default => 'video/mp4',
        };

        return response()->file($disk->path($path), [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
