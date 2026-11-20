<?php

namespace App\Models;

use App\Enums\CaptureMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// The contact details and selfie of a guest who filed a report.
class GuestReporter extends Model
{
    protected $fillable = [
        'report_id', 'first_name', 'last_name', 'phone', 'phone_key', 'email', 'barangay',
        'selfie_path', 'liveness_prompt', 'photo_captured_at', 'selfie_captured_at',
        'photo_capture_method', 'selfie_capture_method', 'ip', 'consented_at',
    ];

    // Tells Laravel which fields get special treatment.
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

    // The report this guest filed.
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }


    // Turns a phone number into a short key used to spot repeat guests.
    public static function phoneKey(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return substr($digits, -10);
    }
}
