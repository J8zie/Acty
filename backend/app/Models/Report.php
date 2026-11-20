<?php

namespace App\Models;

use App\Enums\AssistanceType;
use App\Enums\ReportSource;
use App\Enums\ReportStatus;
use App\Enums\ReviewStatus;
use App\Enums\Urgency;
use App\Support\StatsCache;
use Database\Factories\ReportFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A problem somebody reported (a pothole, a flood, a broken light...).
 *
 * Life of a report:   open -> in_progress (a volunteer accepted it) -> resolved   (or cancelled by an admin)
 * Guest reports also have a review step: review_status pending -> approved (public) or rejected (hidden).
 */
class Report extends Model
{
    /** @use HasFactory<ReportFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id', 'category_id', 'title', 'description', 'assistance_type', 'urgency', 'status', 'barangay',
        'latitude', 'longitude', 'image_path', 'image_disk', 'source', 'review_status', 'reference',
    ];

    protected function casts(): array
    {
        return [
            'assistance_type' => AssistanceType::class,
            'urgency' => Urgency::class,
            'status' => ReportStatus::class,
            'source' => ReportSource::class,
            'review_status' => ReviewStatus::class,
            // decimal columns come back as strings from the database; the map needs real numbers
            'latitude' => 'float',
            'longitude' => 'float',
            'reviewed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // The landing page numbers are cached for a few seconds; any change to a report makes them stale at once.
        static::saved(fn () => StatsCache::forget());
        static::deleted(fn () => StatsCache::forget());
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** The resident who filed it (null for guest reports and for deleted accounts). */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** The volunteer's job on this report. null until somebody accepts it. */
    public function task(): HasOne
    {
        return $this->hasOne(ReportTask::class);
    }

    /** Who is behind a guest report (admins only). */
    public function guestReporter(): HasOne
    {
        return $this->hasOne(GuestReporter::class);
    }

    public function isGuest(): bool
    {
        return $this->source === ReportSource::Guest;
    }

    /** True when the public may see it: everything except guest reports still waiting for / failing review. */
    public function isPublic(): bool
    {
        return $this->review_status === ReviewStatus::Approved;
    }

    /** Only reports the public may see: ->approved(). Guest reports that are pending or rejected are left out. */
    public function scopeApproved(Builder $query): void
    {
        $query->where('review_status', ReviewStatus::Approved->value);
    }

    /** Same as the scope below, for a report you already have in your hand. */
    public function loadApiRelations(bool $withReporter = false): static
    {
        return $this->loadMissing([
            'category:id,name',
            'user:id,first_name,last_name',
            'task.volunteer:id,first_name,last_name',
            ...($withReporter ? ['guestReporter'] : []),
        ]);
    }

    /** Loads everything ReportResource prints, in a few queries instead of a few queries per report. */
    public function scopeWithApiRelations(Builder $query, bool $withReporter = false): void
    {
        $query->with([
            'category:id,name',
            'user:id,first_name,last_name',
            'task.volunteer:id,first_name,last_name',
            ...($withReporter ? ['guestReporter'] : []),
        ]);
    }
}
