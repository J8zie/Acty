<?php

namespace App\Http\Controllers\Api;

use App\Enums\ReportStatus;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CompleteTaskRequest;
use App\Http\Requests\RateReportRequest;
use App\Http\Resources\ReportResource;
use App\Models\Report;
use App\Models\ReportTask;
use App\Support\ImageStore;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * The life of a request after it is filed:  accept -> start -> complete -> rating.
 * Each step checks the step before it, so nobody can skip ahead or do it twice.
 */
class ReportWorkflowController extends Controller
{
    /**
     * POST /reports/{id}/accept (volunteer).
     * Two volunteers may press Accept at the same instant. The report row is locked while we check and write, and the
     * database refuses a second task for the same report (unique index), so exactly one of them wins and the other
     * gets 409 "already taken".
     */
    public function accept(Request $request, Report $report): ReportResource
    {
        Gate::authorize('accept', $report);
        $volunteer = $request->user();
        $limit = (int) config('actify.max_active_tasks');

        try {
            DB::transaction(function () use ($report, $volunteer, $limit) {
                $locked = Report::query()->whereKey($report->id)->lockForUpdate()->firstOrFail();

                if ($locked->status === ReportStatus::InProgress || $locked->task()->exists()) {
                    throw $this->error('This request was already taken.', 409);
                }
                if ($locked->status !== ReportStatus::Open) {
                    throw $this->error('This request is not open any more.', 409);
                }

                $active = ReportTask::query()
                    ->where('volunteer_id', $volunteer->id)
                    ->where('status', '!=', TaskStatus::Complete->value)
                    ->count();
                if ($active >= $limit) {
                    throw $this->error("You already have {$limit} unfinished tasks. Finish one before taking another.", 409);
                }

                ReportTask::create([
                    'report_id' => $locked->id,
                    'volunteer_id' => $volunteer->id,
                    'status' => TaskStatus::Accepted,
                    'accepted_at' => now(),
                ]);
                $locked->update(['status' => ReportStatus::InProgress]);
            });
        } catch (UniqueConstraintViolationException) {
            throw $this->error('This request was already taken.', 409);
        }

        return $this->fresh($report);
    }

    /** POST /reports/{id}/start (the volunteer who holds the task). accepted -> in_progress. */
    public function start(Request $request, Report $report): ReportResource
    {
        Gate::authorize('work', $report);
        $task = $report->task;

        if ($task->status !== TaskStatus::Accepted) {
            throw $this->error($task->status === TaskStatus::Complete
                ? 'This task is already finished.'
                : 'This task was already started.', 409);
        }

        $task->update(['status' => TaskStatus::InProgress, 'started_at' => now()]);

        return $this->fresh($report);
    }

    /** POST /reports/{id}/complete { note, photo?, confirm }. in_progress -> complete; the report becomes resolved. */
    public function complete(CompleteTaskRequest $request, Report $report, ImageStore $images): ReportResource
    {
        $task = $report->task;

        if ($task->status === TaskStatus::Accepted) {
            throw $this->error('Press Start before you finish the task.', 409);
        }
        if ($task->status === TaskStatus::Complete) {
            throw $this->error('This task is already finished.', 409);
        }

        $storedImage = $request->hasFile('photo')
            ? $images->storeReportPhoto($request->file('photo'), 'public', 'volunteer-outcomes')
            : null;
        $oldImagePath = null;

        try {
            DB::transaction(function () use ($task, $report, $request, $storedImage, &$oldImagePath) {
                $lockedTask = ReportTask::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();

                if ($lockedTask->status === TaskStatus::Accepted) {
                    throw $this->error('Press Start before you finish the task.', 409);
                }
                if ($lockedTask->status === TaskStatus::Complete) {
                    throw $this->error('This task is already finished.', 409);
                }

                $oldImagePath = $lockedTask->outcome_image_path;
                if (! $lockedTask->update([
                    'status' => TaskStatus::Complete,
                    'completed_at' => now(),
                    'outcome_note' => $request->validated('note'),
                    'outcome_image_path' => $storedImage?->path,
                ])) {
                    throw new RuntimeException('Unable to save task completion.');
                }
                if (! $report->update(['status' => ReportStatus::Resolved])) {
                    throw new RuntimeException('Unable to resolve the report.');
                }
            });
        } catch (Throwable $e) {
            $images->delete($storedImage?->path, $storedImage?->disk ?? 'public');
            throw $e;
        }

        if ($oldImagePath && $oldImagePath !== $storedImage?->path) {
            $images->delete($oldImagePath, 'public');
        }

        return $this->fresh($report);
    }

    /**
     * POST /reports/{id}/rating { rating 1-5, confirm } (the resident who filed it).
     * Only once the work is complete, and only once: a rating cannot be changed afterwards (that is why the app
     * asks "Are you sure?" first).
     */
    public function rating(RateReportRequest $request, Report $report): ReportResource
    {
        $task = $report->task;

        if (! $task || $task->status !== TaskStatus::Complete) {
            throw ValidationException::withMessages(['rating' => ['You can rate the volunteer once the work is finished.']]);
        }

        // "update where rating is still empty": if two clicks arrive together only one changes a row.
        $changed = ReportTask::query()
            ->whereKey($task->id)
            ->whereNull('rating')
            ->update(['rating' => $request->validated('rating'), 'rated_at' => now()]);

        if ($changed === 0) {
            throw ValidationException::withMessages(['rating' => ['You already rated this report.']]);
        }

        return $this->fresh($report);
    }

    private function fresh(Report $report): ReportResource
    {
        return new ReportResource(Report::query()->withApiRelations()->findOrFail($report->id));
    }
}
