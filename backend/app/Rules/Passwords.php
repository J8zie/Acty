<?php

namespace App\Rules;

use Illuminate\Validation\Rules\Password;

// The password rules every account must follow.
final class Passwords
{

    // Returns the list of password rules.
    public static function rules(): array
    {
        return ['required', 'string', 'max:72', Password::min(8)->letters()->numbers()];
    }
}
