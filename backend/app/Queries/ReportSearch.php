<?php

namespace App\Queries;

use App\Enums\ReviewStatus;
use App\Enums\ReportSource;
use App\Http\Requests\ListReportsRequest;
use App\Models\Report;
use App\Models\User;
use App\Support\Like;
use Illuminate\Database\Eloquent\Builder;

// Builds the database query for the report list from the filters sent.
final class ReportSearch
{
    // Keeps the request and the person looking at it.
    public function __construct(private readonly ListReportsRequest $request, private readonly ?User $viewer) {}

    // Applies review rules, filters, search and sorting to the query.
    public function query(): Builder
    {
        $r = $this->request;
        $isAdmin = (bool) $this->viewer?->isAdmin();

        $query = Report::query()->withApiRelations(withReporter: $isAdmin);


        if ($r->query('review') === 'pending') {

            $query->where('source', ReportSource::Guest->value)->where('review_status', ReviewStatus::Pending->value);
        } else {

            $query->approved();
        }


        match ($r->query('scope')) {
            'mine' => $query->where('user_id', $this->viewer?->id),
            'tasks' => $query->whereHas('task', fn (Builder $t) => $t->where('volunteer_id', $this->viewer?->id)),
            default => null,
        };


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


        $this->search($query, (string) $r->query('q', ''), $isAdmin);


        $this->sort($query, (string) $r->query('sort', 'newest'));

        return $query;
    }


    // Adds a text search across title, description, place and category.
    private function search(Builder $query, string $text, bool $isAdmin): void
    {
        $words = array_slice(preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [], 0, 6);

        foreach ($words as $word) {

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

    // Orders the results by the chosen sort.
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
