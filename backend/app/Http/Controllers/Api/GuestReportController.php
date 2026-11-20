<?php

namespace App\Http\Controllers\Api;

use App\Enums\ReportSource;
use App\Enums\ReportStatus;
use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\GuestReportRequest;
use App\Models\GuestReporter;
use App\Models\Report;
use App\Support\ImageStore;
use App\Support\VideoStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

// Handles reports sent in by guests who have no account.
class GuestReportController extends Controller
{
    // Saves a guest's report, photos and contact details.
    public function store(GuestReportRequest $request, ImageStore $images, VideoStore $videos): JsonResponse
    {
        $data = $request->validated();
        $phoneKey = GuestReporter::phoneKey($data['phone']);

        $this->enforceLimits($request->ip(), $phoneKey);

        $photo = $images->storeReportPhoto($request->file('photo'), 'cloudinary-private', 'guest-reports', 'photo');
        $video = null;
        try {
            $video = $request->hasFile('video')
                ? $videos->storeReportVideo($request->file('video'), 'cloudinary-private', 'guest-reports', 'video')
                : null;
            $selfie = $images->storeSelfie($request->file('selfie'));
        } catch (Throwable $e) {
            $images->delete($photo->path, $photo->disk);
            $videos->delete($video?->path, $video?->disk ?? 'private');
            throw $e;
        }

        try {
            $report = DB::transaction(function () use ($data, $request, $phoneKey, $photo, $selfie, $video) {
                $report = Report::create([
                    'user_id' => null,
                    'category_id' => $data['category_id'],
                    'title' => $data['title'],
                    'description' => $data['description'],
                    'assistance_type' => $data['assistance_type'],
                    'urgency' => $data['urgency'],
                    'barangay' => $data['barangay'],
                    'latitude' => $data['latitude'],
                    'longitude' => $data['longitude'],
                    'status' => ReportStatus::Open,
                    'source' => ReportSource::Guest,
                    'review_status' => ReviewStatus::Pending,
                    'image_path' => $photo->path,
                    'image_disk' => $photo->disk,
                    'video_path' => $video?->path,
                    'video_disk' => $video?->disk,
                    'reference' => $this->newReference(),
                ]);

                GuestReporter::create([
                    'report_id' => $report->id,
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'phone' => $data['phone'],
                    'phone_key' => $phoneKey,
                    'email' => $data['email'] ?? null,
                    'barangay' => $data['reporter_barangay'],
                    'selfie_path' => $selfie->path,
                    'liveness_prompt' => $data['liveness_prompt'],
                    'photo_captured_at' => $data['photo_captured_at'],
                    'selfie_captured_at' => $data['selfie_captured_at'],
                    'photo_capture_method' => $data['photo_capture_method'],
                    'selfie_capture_method' => $data['selfie_capture_method'],
                    'ip' => $request->ip(),
                    'consented_at' => now(),
                ]);

                return $report;
            });
        } catch (Throwable $e) {
            $images->delete($photo->path, $photo->disk);
            $images->delete($selfie->path, $selfie->disk);
            $videos->delete($video?->path, $video?->disk ?? 'private');
            throw $e;
        }

        return response()->json([
            'reference' => $report->reference,
            'message' => "Thank you. Your report was received and will appear once an admin has checked it. Your reference is {$report->reference}.",
        ], 201);
    }

    // Stops people from sending too many guest reports.
    private function enforceLimits(?string $ip, string $phoneKey): void
    {
        $perIp = (int) config('actify.guest.per_ip_per_hour');
        $perPhone = (int) config('actify.guest.per_phone_per_day');

        $fromIp = $ip === null ? 0 : GuestReporter::query()->where('ip', $ip)->where('created_at', '>=', now()->subHour())->count();
        $fromPhone = GuestReporter::query()->where('phone_key', $phoneKey)->where('created_at', '>=', now()->subDay())->count();

        if ($fromIp >= $perIp || $fromPhone >= $perPhone) {
            throw $this->error('You have sent too many reports. Please try again later.', 429);
        }
    }

    // Makes a new short reference code for the report.
    private function newReference(): string
    {
        do {
            $reference = 'ACT-'.random_int(100000, 999999);
        } while (Report::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
