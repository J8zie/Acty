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
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * POST /reports/guest : a report from somebody WITHOUT an account.
 *
 * What protects it (a form anyone on the internet can post to):
 *   1. validation: names, phone, both pictures present and decodable, capture times close to the server clock, consent;
 *   2. throttles counted in the database: per IP address per hour, and per phone number per day;
 *   3. the report is saved hidden (review_status "pending") and its pictures go to the PRIVATE disk;
 *   4. an admin must approve it before the public can see it (ReportReviewController).
 */
class GuestReportController extends Controller
{
    public function store(GuestReportRequest $request, ImageStore $images): JsonResponse
    {
        $data = $request->validated();
        $phoneKey = GuestReporter::phoneKey($data['phone']);

        $this->enforceLimits($request->ip(), $phoneKey);

        // Pictures first (they can still fail: "this file is not a picture"). If the second one fails, drop the first.
        $photo = $images->storeReportPhoto($request->file('photo'), 'private', 'guest-reports', 'photo');
        try {
            $selfie = $images->storeSelfie($request->file('selfie'));
        } catch (Throwable $e) {
            $images->delete($photo->path, $photo->disk);
            throw $e;
        }

        try {
            $report = DB::transaction(function () use ($data, $request, $phoneKey, $photo, $selfie) {
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
            throw $e;
        }

        return response()->json([
            'reference' => $report->reference,
            'message' => "Thank you. Your report was received and will appear once an admin has checked it. Your reference is {$report->reference}.",
        ], 201);
    }

    /** 429 when this address or this phone number has sent too many reports recently (rejected ones count too). */
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

    /** ACT-123456: short enough to read out on the phone, random so it cannot be guessed from the previous one. */
    private function newReference(): string
    {
        do {
            $reference = 'ACT-'.random_int(100000, 999999);
        } while (Report::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
