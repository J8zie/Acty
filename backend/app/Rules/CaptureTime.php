<?php

namespace App\Rules;

use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Throwable;

// Checks that a photo's timestamp is recent and not in the future.
class CaptureTime implements ValidationRule
{
    // Remembers how the photo was taken and what "now" is.
    public function __construct(private readonly ?string $method = 'live', private readonly ?Carbon $now = null) {}

    // Fails when the time is not a real date or is outside the allowed window.
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $time = Carbon::parse((string) $value);
        } catch (Throwable) {
            $fail('The :attribute must be a valid date and time.');

            return;
        }

        $now = $this->now ?? now();
        $window = $this->method === 'camera-app'
            ? (int) config('actify.guest.camera_app_window_minutes')
            : (int) config('actify.guest.live_window_minutes');
        $skew = (int) config('actify.guest.future_skew_minutes');

        if ($time->gt($now->copy()->addMinutes($skew))) {
            $fail('The :attribute is ahead of our clock. Check the date and time on your device and try again.');

            return;
        }

        if ($time->lt($now->copy()->subMinutes($window))) {
            $fail("The :attribute is too old (more than {$window} minutes). Take the picture again and send it right away.");
        }
    }
}
