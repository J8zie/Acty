<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Support\StatsCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class StatsController extends Controller
{
    /**
     * GET /stats -> { total, open, in_progress, resolved } for the landing page.
     * Counts only reports the public can see. Cancelled ones are in `total` only. Cached a few seconds; any change
     * to a report clears the cache at once (Report::booted()).
     */
    public function __invoke(): JsonResponse
    {
        $stats = Cache::remember(StatsCache::KEY, (int) config('actify.stats_cache_seconds'), function () {
            $counts = Report::query()->approved()->toBase()
                ->selectRaw('status, count(*) as n')
                ->groupBy('status')
                ->pluck('n', 'status');

            return [
                'total' => (int) $counts->sum(),
                'open' => (int) ($counts['open'] ?? 0),
                'in_progress' => (int) ($counts['in_progress'] ?? 0),
                'resolved' => (int) ($counts['resolved'] ?? 0),
            ];
        });

        return response()->json($stats);
    }
}
