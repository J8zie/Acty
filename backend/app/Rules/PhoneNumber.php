<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

// Checks that a phone number looks real.
class PhoneNumber implements ValidationRule
{
    // Fails when the text is not a plausible phone number.
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $text = is_string($value) ? trim($value) : '';
        $digits = preg_replace('/\D+/', '', $text) ?? '';

        $shapeOk = (bool) preg_match('/^\+?\(?[0-9][0-9 ()\-.]*$/', $text);

        if (! $shapeOk || strlen($digits) < 7 || strlen($digits) > 15) {
            $fail('The :attribute must be a phone number such as 0912 345 6789 or +63 912 345 6789.');
        }
    }
}
