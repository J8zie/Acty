<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Database\Factories\ReportTaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "Volunteer X is working on report Y". One row per report (the unique index on report_id).
 *
 * accepted -> in_progress (volunteer pressed Start) -> complete (with a note and optional photo). After that the
 * resident who filed the report may rate it once (1-5).
 */
class ReportTask extends Model
{
    /** @use HasFactory<ReportTaskFactory> */
    use HasFactory;

    protected $fillable = [
        'report_id', 'volunteer_id', 'status', 'accepted_at', 'started_at', 'completed_at',
        'outcome_note', 'outcome_image_path', 'rating', 'rated_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'accepted_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'rated_at' => 'datetime',
            'rating' => 'integer',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function volunteer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'volunteer_id');
    }

    public function isActive(): bool
    {
        return $this->status !== TaskStatus::Complete;
    }
}
