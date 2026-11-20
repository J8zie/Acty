<?php

namespace App\Models;

use App\Enums\AssistanceType;
use App\Enums\ReportSource;
use App\Enums\ReportStatus;
use App\Enums\ReviewStatus;
use App\Enums\Urgency;
use App\Support\StatsCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

// A problem someone reported that volunteers can help with.
class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'category_id', 'title', 'description', 'assistance_type', 'urgency', 'status', 'barangay',
        'latitude', 'longitude', 'image_path', 'image_disk', 'video_path', 'video_disk', 'source', 'review_status', 'reference',
    ];

    // Tells Laravel which fields get special treatment.
    protected function casts(): array
    {
        return [
            'assistance_type' => AssistanceType::class,
            'urgency' => Urgency::class,
            'status' => ReportStatus::class,
            'source' => ReportSource::class,
            'review_status' => ReviewStatus::class,

            'latitude' => 'float',
            'longitude' => 'float',
            'reviewed_at' => 'datetime',
        ];
    }

    // Clears the cached stats whenever a report is saved or deleted.
    protected static function booted(): void
    {

        static::saved(fn () => StatsCache::forget());
        static::deleted(fn () => StatsCache::forget());
    }

    // The category this report belongs to.
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    // The account that filed this report, if any.
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // The admin who reviewed this guest report.
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // The volunteer task attached to this report.
    public function task(): HasOne
    {
        return $this->hasOne(ReportTask::class);
    }

    // The guest's contact details, if this came from a guest.
    public function guestReporter(): HasOne
    {
        return $this->hasOne(GuestReporter::class);
    }

    // Checks if this report came from a guest without an account.
    public function isGuest(): bool
    {
        return $this->source === ReportSource::Guest;
    }

    // Checks if this report passed admin review and is public.
    public function isPublic(): bool
    {
        return $this->review_status === ReviewStatus::Approved;
    }

    // Only shows reports an admin approved.
    public function scopeApproved(Builder $query): void
    {
        $query->where('review_status', ReviewStatus::Approved->value);
    }

    // Loads the relations the API answer needs onto this report.
    public function loadApiRelations(bool $withReporter = false): static
    {
        return $this->loadMissing([
            'category:id,name',
            'user:id,first_name,last_name',
            'task.volunteer:id,first_name,last_name',
            ...($withReporter ? ['guestReporter'] : []),
        ]);
    }

    // Eager-loads the relations the API answer needs in a list query.
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
