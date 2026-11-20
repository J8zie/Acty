<?php

namespace App\Rules;

use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Throwable;

/**
 * "When was this picture taken?" must be close to the SERVER's clock.
 *
 * A browser can claim anything, but a picture "taken" three days ago (a gallery upload) or tomorrow is a red flag.
 *   - method "live"       : taken inside the page, at most config('actify.guest.live_window_minutes') ago
 *   - method "camera-app" : phone fallback, the file must be at most config('actify.guest.camera_app_window_minutes') old
 * A little clock difference between the phone and the server is allowed (future_skew_minutes).
 */
class CaptureTime implements ValidationRule
{
    public function __construct(private readonly ?string $method = 'live', private readonly ?Carbon $now = null) {}

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
