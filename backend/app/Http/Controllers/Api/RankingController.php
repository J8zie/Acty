<?php

namespace App\Http\Controllers\Api;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Models\ReportTask;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// Ranks volunteers by finished tasks and ratings.
class RankingController extends Controller
{

    // Returns the top volunteers, optionally for one barangay.
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'barangay' => ['nullable', 'string', 'max:80'],
            'limit' => ['nullable', 'integer', 'between:1,100'],
        ]);
        $barangay = $request->query('barangay');
        $limit = (int) ($request->query('limit') ?: 50);

        $rows = ReportTask::query()
            ->join('reports', 'reports.id', '=', 'report_tasks.report_id')
            ->where('report_tasks.status', TaskStatus::Complete->value)
            ->whereNotNull('report_tasks.volunteer_id')
            ->when($barangay, fn ($q) => $q->whereRaw('lower(reports.barangay) = ?', [mb_strtolower($barangay)]))
            ->toBase()
            ->get(['report_tasks.volunteer_id', 'report_tasks.rating', 'reports.barangay']);

        $ranked = $rows->groupBy('volunteer_id')->map(function ($tasks, $volunteerId) {
            $ratings = $tasks->pluck('rating')->filter()->values();
            $places = $tasks->pluck('barangay')->countBy()->all();

            uksort($places, fn ($a, $b) => [$places[$b], $a] <=> [$places[$a], $b]);

            return [
                'id' => (int) $volunteerId,
                'barangay' => (string) array_key_first($places),
                'completed_count' => $tasks->count(),
                'average_rating' => $ratings->isEmpty() ? null : round($ratings->avg(), 1),
                'ratings_count' => $ratings->count(),
            ];
        })->values();

        $people = User::query()->whereIn('id', $ranked->pluck('id'))->get(['id', 'first_name', 'last_name'])->keyBy('id');

        $out = $ranked
            ->filter(fn ($row) => $people->has($row['id']))
            ->sort(fn ($a, $b) => [$b['completed_count'], $b['average_rating'] ?? 0, $b['ratings_count'], $a['id']]
                <=> [$a['completed_count'], $a['average_rating'] ?? 0, $a['ratings_count'], $b['id']])
            ->take($limit)
            ->map(fn ($row) => [
                'id' => $row['id'],
                'first_name' => $people[$row['id']]->first_name,
                'last_name' => $people[$row['id']]->last_name,
                'barangay' => $row['barangay'],
                'completed_count' => $row['completed_count'],
                'average_rating' => $row['average_rating'],
                'ratings_count' => $row['ratings_count'],
            ])
            ->values();

        return response()->json($out);
    }
}
