<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

// Checks that a phone number is digits only.
class PhoneNumber implements ValidationRule
{
    // Fails when the text is not 7 to 15 digits.
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $text = is_string($value) ? trim($value) : '';

        if (! preg_match('/^\d{7,15}$/', $text)) {
            $fail('The :attribute must be a phone number with digits only, such as 09123456789.');
        }
    }
}
