<?php

namespace App\Http\Controllers\Api;

use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewReportRequest;
use App\Http\Resources\ReportResource;
use App\Models\Report;
use App\Support\ImageStore;
use App\Support\VideoStore;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

// Lets an admin approve or reject a guest report.
class ReportReviewController extends Controller
{
    // Marks a pending guest report as approved or rejected.
    public function __invoke(ReviewReportRequest $request, Report $report, ImageStore $images, VideoStore $videos): ReportResource
    {
        if (! $report->isGuest()) {
            throw $this->error('Only reports filed without an account need a review.', 409);
        }
        if ($report->review_status !== ReviewStatus::Pending) {
            throw $this->error('This report was already reviewed.', 409);
        }

        $approve = $request->validated('decision') === 'approve';
        $privatePath = $report->image_path;
        $privateVideoPath = $report->video_path;
        $storedImage = $approve && $privatePath && $report->image_disk === 'private'
            ? $images->promoteGuestReportPhoto($privatePath)
            : null;
        $storedVideo = $approve && $privateVideoPath && $report->video_disk === 'private'
            ? $videos->promoteGuestVideo($privateVideoPath)
            : null;

        try {
            DB::transaction(function () use ($report, $request, $approve, $storedImage, $storedVideo) {
                $lockedReport = Report::query()->whereKey($report->id)->lockForUpdate()->firstOrFail();

                if ($lockedReport->review_status !== ReviewStatus::Pending) {
                    throw $this->error('This report was already reviewed.', 409);
                }

                if ($storedImage) {
                    $lockedReport->image_path = $storedImage->path;
                    $lockedReport->image_disk = $storedImage->disk;
                }
                if ($storedVideo) {
                    $lockedReport->video_path = $storedVideo->path;
                    $lockedReport->video_disk = $storedVideo->disk;
                }

                $lockedReport->review_status = $approve ? ReviewStatus::Approved : ReviewStatus::Rejected;
                $lockedReport->reviewed_by = $request->user()->id;
                $lockedReport->reviewed_at = now();

                if (! $lockedReport->save()) {
                    throw new RuntimeException('Unable to save the report review.');
                }
            });
        } catch (Throwable $e) {
            if ($storedImage) {
                $images->delete($storedImage->path, $storedImage->disk);
            }
            if ($storedVideo) {
                $videos->delete($storedVideo->path, $storedVideo->disk);
            }

            throw $e;
        }

        if ($storedImage) {
            $images->delete($privatePath, 'private');
        }
        if ($storedVideo) {
            $videos->delete($privateVideoPath, 'private');
        }

        return new ReportResource($report->refresh()->loadApiRelations(withReporter: true));
    }
}
