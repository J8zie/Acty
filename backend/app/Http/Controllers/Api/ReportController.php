<?php

namespace App\Http\Controllers\Api;

use App\Enums\ReportSource;
use App\Enums\ReportStatus;
use App\Enums\ReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmRequest;
use App\Http\Requests\ListReportsRequest;
use App\Http\Requests\StoreReportRequest;
use App\Http\Requests\UpdateReportStatusRequest;
use App\Http\Resources\ReportResource;
use App\Models\Report;
use App\Queries\ReportSearch;
use App\Support\ImageStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

// Lists, shows, creates, edits and deletes reports.
class ReportController extends Controller
{

    // Lists reports with filters, search and paging.
    public function index(ListReportsRequest $request): JsonResponse
    {
        $viewer = $request->user('sanctum');

        if (($request->query('scope') || $request->query('review')) && ! $viewer) {
            throw $this->error('Please sign in first.', 401);
        }
        if ($request->query('review') && ! $viewer->isAdmin()) {
            throw $this->error('You are not allowed to do that.', 403);
        }

        $query = (new ReportSearch($request, $viewer))->query();
        [$limit, $page] = $this->paging($request);

        $total = (clone $query)->toBase()->getCountForPagination();
        $reports = $query->forPage($page, $limit)->get();

        return ReportResource::collection($reports)
            ->response()
            ->withHeaders($this->pagingHeaders($total, $page, $limit));
    }


    // Shows one report if the person may see it.
    public function show(Request $request, Report $report): ReportResource
    {
        $viewer = $request->user('sanctum');

        if (! $report->isPublic() && ! $viewer?->isAdmin()) {
            throw $this->error('We could not find that.', 404);
        }

        return new ReportResource($report->loadApiRelations(withReporter: (bool) $viewer?->isAdmin()));
    }


    // Creates a report from a signed-in resident.
    public function store(StoreReportRequest $request, ImageStore $images): JsonResponse
    {
        $data = $request->validated();
        $storedImage = $request->hasFile('photo') ? $images->storeReportPhoto($request->file('photo')) : null;

        try {
            $report = Report::create([
                'user_id' => $request->user()->id,
                'category_id' => $data['category_id'],
                'title' => $data['title'],
                'description' => $data['description'],
                'assistance_type' => $data['assistance_type'],
                'urgency' => $data['urgency'],
                'barangay' => $data['barangay'],
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'status' => ReportStatus::Open,
                'source' => ReportSource::Account,
                'review_status' => ReviewStatus::Approved,
                'image_path' => $storedImage?->path,
                'image_disk' => $storedImage?->disk ?? 'public',
            ]);
        } catch (Throwable $e) {
            $images->delete($storedImage?->path, $storedImage?->disk ?? 'public');
            throw $e;
        }

        return (new ReportResource($report->loadApiRelations()))->response()->setStatusCode(201);
    }


    // Changes a report's status (admin only).
    public function update(UpdateReportStatusRequest $request, Report $report): ReportResource
    {
        $status = ReportStatus::from($request->validated('status'));

        if ($report->review_status === ReviewStatus::Pending) {
            throw $this->error('Approve or reject this guest report first.', 409);
        }
        if ($report->review_status === ReviewStatus::Rejected) {
            throw $this->error('This guest report was rejected.', 409);
        }

        DB::transaction(function () use ($report, $status) {
            if ($status === ReportStatus::Open && $report->task) {
                if (! $report->task->isActive()) {
                    throw $this->error('A finished request cannot be reopened.', 409);
                }
                $report->task->delete();
            }
            $report->status = $status;
            $report->save();
        });

        $report->unsetRelation('task');

        return new ReportResource($report->loadApiRelations(withReporter: true));
    }


    // Deletes a report and all its pictures.
    public function destroy(ConfirmRequest $request, Report $report, ImageStore $images): Response
    {
        $report->loadMissing(['task', 'guestReporter']);

        $files = [
            [$report->image_path, $report->image_disk],
            [$report->task?->outcome_image_path, 'public'],
            [$report->guestReporter?->selfie_path, 'private'],
        ];

        if (! $report->delete()) {
            throw new RuntimeException('Unable to delete the report.');
        }

        foreach ($files as [$path, $disk]) {
            $images->delete($path, $disk);
        }

        return response()->noContent();
    }
}
