<?php

namespace App\Rules;

use Illuminate\Validation\Rules\Password;

/** One definition of "a good enough password" for sign-up and for changing it. */
final class Passwords
{
    /** At least 8 characters with a letter and a number; 72 is the most bcrypt can use. */
    public static function rules(): array
    {
        return ['required', 'string', 'max:72', Password::min(8)->letters()->numbers()];
    }
}
