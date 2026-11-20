<?php

namespace App\Models;

use App\Enums\CaptureMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The personal details and proof (selfie) behind a report filed without an account.
 * Only admins ever receive this data (see ReportResource and ReporterResource).
 */
class GuestReporter extends Model
{
    protected $fillable = [
        'report_id', 'first_name', 'last_name', 'phone', 'phone_key', 'email', 'barangay',
        'selfie_path', 'liveness_prompt', 'photo_captured_at', 'selfie_captured_at',
        'photo_capture_method', 'selfie_capture_method', 'ip', 'consented_at',
    ];

    protected function casts(): array
    {
        return [
            'photo_captured_at' => 'datetime',
            'selfie_captured_at' => 'datetime',
            'consented_at' => 'datetime',
            'photo_capture_method' => CaptureMethod::class,
            'selfie_capture_method' => CaptureMethod::class,
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    /** "+63 912-345-6789" -> "9123456789": the last 10 digits, used to spot the same person sending twice. */
    public static function phoneKey(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return substr($digits, -10);
    }
}
