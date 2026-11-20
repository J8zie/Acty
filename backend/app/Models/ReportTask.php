<?php

namespace App\Models;

use App\Enums\TaskStatus;
use Database\Factories\ReportTaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// A volunteer's job on one report.
class ReportTask extends Model
{

    use HasFactory;

    protected $fillable = [
        'report_id', 'volunteer_id', 'status', 'accepted_at', 'started_at', 'completed_at',
        'outcome_note', 'outcome_image_path', 'rating', 'rated_at',
    ];

    // Tells Laravel which fields get special treatment.
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

    // The report this task belongs to.
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    // The volunteer doing this task.
    public function volunteer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'volunteer_id');
    }

    // Checks if the task is not finished yet.
    public function isActive(): bool
    {
        return $this->status !== TaskStatus::Complete;
    }
}
