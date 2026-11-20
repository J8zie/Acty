<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// Checks the username and password sent when someone logs in.
class LoginRequest extends FormRequest
{
    // Anyone may try to log in.
    public function authorize(): bool
    {
        return true;
    }

    // The rules the login form must pass.
    public function rules(): array
    {

        return [
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }
}
