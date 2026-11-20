<?php

namespace App\Queries;

use App\Enums\ReviewStatus;
use App\Enums\ReportSource;
use App\Http\Requests\ListReportsRequest;
use App\Models\Report;
use App\Models\User;
use App\Support\Like;
use Illuminate\Database\Eloquent\Builder;

/**
 * Turns the filters of GET /reports into one database query. All of the search / filter / sort logic of the
 * Reports list lives in this one file.
 */
final class ReportSearch
{
    public function __construct(private readonly ListReportsRequest $request, private readonly ?User $viewer) {}

    public function query(): Builder
    {
        $r = $this->request;
        $isAdmin = (bool) $this->viewer?->isAdmin();

        $query = Report::query()->withApiRelations(withReporter: $isAdmin);

        // 1. Who may see which reports
        if ($r->query('review') === 'pending') {
            // admin queue: guest reports waiting for a decision (the controller already checked the viewer is an admin)
            $query->where('source', ReportSource::Guest->value)->where('review_status', ReviewStatus::Pending->value);
        } else {
            // everybody else: only approved reports (guest reports that are pending or rejected never show up)
            $query->approved();
        }

        // 2. "My reports" and "My tasks"
        match ($r->query('scope')) {
            'mine' => $query->where('user_id', $this->viewer?->id),
            'tasks' => $query->whereHas('task', fn (Builder $t) => $t->where('volunteer_id', $this->viewer?->id)),
            default => null,
        };

        // 3. Filters
        if ($statuses = $r->list('status')) {
            $query->whereIn('status', $statuses);
        }
        if ($r->filled('category_id')) {
            $query->where('category_id', (int) $r->query('category_id'));
        }
        if ($urgencies = $r->list('urgency')) {
            $query->whereIn('urgency', $urgencies);
        }
        if ($types = $r->list('assistance_type')) {
            $query->whereIn('assistance_type', $types);
        }
        if ($r->filled('barangay')) {
            $query->whereRaw('lower(reports.barangay) = ?', [mb_strtolower((string) $r->query('barangay'))]);
        }
        if ($r->filled('from')) {
            $query->whereDate('reports.created_at', '>=', $r->query('from'));
        }
        if ($r->filled('to')) {
            $query->whereDate('reports.created_at', '<=', $r->query('to'));
        }

        // 4. Search words
        $this->search($query, (string) $r->query('q', ''), $isAdmin);

        // 5. Order (id is the last tie-breaker so paging never shows a report twice)
        $this->sort($query, (string) $r->query('sort', 'newest'));

        return $query;
    }

    /**
     * "flood school" finds reports that mention BOTH words somewhere in the title, description, barangay or category.
     * "#12" or "12" also finds report number 12. Admins can search a guest's reference ("ACT-123456") too.
     */
    private function search(Builder $query, string $text, bool $isAdmin): void
    {
        $words = array_slice(preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 6);

        foreach ($words as $word) {
            // % and _ are wildcards in LIKE; Like handles them (and ILIKE on PostgreSQL, see app/Support/Like.php)
            $like = Like::contains($word);

            $query->where(function (Builder $w) use ($word, $like, $isAdmin) {
                $w->whereRaw(Like::sql('reports.title'), [$like])
                    ->orWhereRaw(Like::sql('reports.description'), [$like])
                    ->orWhereRaw(Like::sql('reports.barangay'), [$like])
                    ->orWhereHas('category', fn (Builder $c) => $c->whereRaw(Like::sql('categories.name'), [$like]));

                $number = ltrim($word, '#');
                if ($number !== '' && ctype_digit($number) && strlen($number) <= 9) {
                    $w->orWhere('reports.id', (int) $number);
                }
                if ($isAdmin) {
                    $w->orWhereRaw(Like::sql('reports.reference'), [$like]);
                }
            });
        }
    }

    private function sort(Builder $query, string $sort): void
    {
        match ($sort) {
            'oldest' => $query->orderBy('reports.created_at')->orderBy('reports.id'),
            'urgency' => $query->orderByRaw("CASE reports.urgency WHEN 'high' THEN 0 WHEN 'medium' THEN 1 ELSE 2 END")
                ->orderByDesc('reports.created_at')->orderByDesc('reports.id'),
            'status' => $query->orderByRaw("CASE reports.status WHEN 'open' THEN 0 WHEN 'in_progress' THEN 1 WHEN 'resolved' THEN 2 ELSE 3 END")
                ->orderByDesc('reports.created_at')->orderByDesc('reports.id'),
            default => $query->orderByDesc('reports.created_at')->orderByDesc('reports.id'),
        };
    }
}
