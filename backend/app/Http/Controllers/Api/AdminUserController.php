<?php

namespace App\Http\Controllers\Api;

use App\Enums\ReportStatus;
use App\Enums\Role;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmRequest;
use App\Http\Requests\ListUsersRequest;
use App\Http\Requests\UpdateUserRoleRequest;
use App\Http\Resources\UserResource;
use App\Models\Report;
use App\Models\ReportTask;
use App\Models\User;
use App\Support\ImageStore;
use App\Support\Like;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** The People page (admin only). */
class AdminUserController extends Controller
{
    /** GET /admin/users?q=&role=&sort=&limit=&page= */
    public function index(ListUsersRequest $request): JsonResponse
    {
        $query = User::query();

        // every word must appear in the username, the name or the email ("maria san" finds Maria Santos)
        $words = array_slice(preg_split('/\s+/', trim((string) $request->query('q', '')), -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 5);
        foreach ($words as $word) {
            $like = Like::contains($word);
            $query->where(function (Builder $w) use ($like) {
                $w->whereRaw(Like::sql('username'), [$like])
                    ->orWhereRaw(Like::sql('first_name'), [$like])
                    ->orWhereRaw(Like::sql('last_name'), [$like])
                    ->orWhereRaw(Like::sql('email'), [$like]);
            });
        }

        if ($request->filled('role')) {
            $query->where('role', $request->query('role'));
        }

        match ($request->query('sort', 'newest')) {
            'oldest' => $query->orderBy('id'),
            'name' => $query->orderBy('first_name')->orderBy('last_name')->orderBy('id'),
            'role' => $query->orderBy('role')->orderBy('first_name')->orderBy('id'),
            default => $query->orderByDesc('id'),
        };

        [$limit, $page] = $this->paging($request);
        $total = (clone $query)->toBase()->getCountForPagination();

        return UserResource::collection($query->forPage($page, $limit)->get())
            ->response()
            ->withHeaders($this->pagingHeaders($total, $page, $limit));
    }

    /**
     * PATCH /admin/users/{id} { role, confirm }
     * You cannot change your own role (an admin who demotes themselves could lock everybody out). A volunteer who
     * stops being a volunteer hands back their unfinished tasks.
     */
    public function update(UpdateUserRoleRequest $request, User $user): UserResource
    {
        $new = Role::from($request->validated('role'));

        if ($user->is($request->user())) {
            throw $this->error('You cannot change your own role.', 409);
        }
        if ($user->isAdmin() && $new !== Role::Admin && $this->isLastAdmin()) {
            throw $this->error('There must always be at least one admin.', 409);
        }

        DB::transaction(function () use ($user, $new) {
            if ($user->isVolunteer() && $new !== Role::Volunteer) {
                $this->releaseTasks($user);
            }
            $user->role = $new;
            $user->save();
        });

        return new UserResource($user);
    }

    /** DELETE /admin/users/{id} { confirm } -> 204. Their reports stay (shown as "a neighbour"); unfinished tasks are handed back. */
    public function destroy(ConfirmRequest $request, User $user, ImageStore $images): Response
    {
        if ($user->is($request->user())) {
            throw $this->error('You cannot remove your own account here.', 409);
        }
        if ($user->isAdmin() && $this->isLastAdmin()) {
            throw $this->error('There must always be at least one admin.', 409);
        }

        $photo = $user->photo_path;

        DB::transaction(function () use ($user) {
            $this->releaseTasks($user);
            $user->tokens()->delete(); // they are signed out everywhere at once

            if (! $user->delete()) {
                throw new RuntimeException('Unable to delete the account.');
            }
        });

        $images->delete($photo);

        return response()->noContent();
    }

    private function isLastAdmin(): bool
    {
        return User::query()->where('role', Role::Admin->value)->count() <= 1;
    }

    /** Unfinished tasks of this person go back into the open list so another volunteer can take them. */
    private function releaseTasks(User $user): void
    {
        ReportTask::query()
            ->where('volunteer_id', $user->id)
            ->where('status', '!=', TaskStatus::Complete->value)
            ->get()
            ->each(function (ReportTask $task) {
                Report::query()->whereKey($task->report_id)->first()?->update(['status' => ReportStatus::Open]);
                $task->delete();
            });
    }
}
