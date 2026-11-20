<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Support\ImageStore;
use App\Support\VideoStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

// Sends private report pictures and videos to people who have a signed link.
class ImageController extends Controller
{
    // Returns a private photo, selfie or video for a report.
    public function private(Report $report, string $kind, ImageStore $images, VideoStore $videos): StreamedResponse|BinaryFileResponse|RedirectResponse
    {
        $path = match ($kind) {
            'selfie' => $report->guestReporter?->selfie_path,
            'photo' => $report->image_disk === 'private' ? $report->image_path : null,
            'video' => $report->video_disk === 'private' ? $report->video_path : null,
            default => null,
        };

        if (! $path) {
            throw $this->error('We could not find that.', 404);
        }

        if (str_starts_with($path, 'cloudinary:')) {
            $url = $kind === 'video'
                ? $videos->url($path, 'public')
                : $images->url($path, 'public');

            if (! $url) {
                throw $this->error('We could not find that.', 404);
            }

            return redirect()->away($url);
        }

        if ($kind === 'video') {
            return $this->video($report, $path);
        }

        $disk = Storage::disk('private');

        if (! $disk->exists($path)) {
            throw $this->error('We could not find that.', 404);
        }

        return $disk->response($path, null, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    // Streams a private report video with range support so it can be played.
    private function video(Report $report, string $path): BinaryFileResponse
    {
        $disk = Storage::disk('private');

        if (! $disk->exists($path)) {
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
