<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * "0912 345 6789", "+63 912 345 6789", "(082) 221-1234": digits with an optional leading + (or an opening bracket), and spaces, dashes,
 * dots or brackets between them. 7 to 15 digits in all (the international limit is 15).
 */
class PhoneNumber implements ValidationRule
{
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
