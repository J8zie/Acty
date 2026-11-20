<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Rules\Passwords;
use App\Rules\Patterns;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // "Maria" and "maria" are one account; emails are compared in lowercase too.
        $this->merge([
            'username' => is_string($this->username) ? mb_strtolower(trim($this->username)) : $this->username,
            'email' => is_string($this->email) ? mb_strtolower(trim($this->email)) : $this->email,
        ]);
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'min:3', 'max:30', 'regex:'.Patterns::USERNAME, 'unique:users,username'],
            'first_name' => ['required', 'string', 'min:2', 'max:60', 'regex:'.Patterns::NAME],
            'last_name' => ['required', 'string', 'min:2', 'max:60', 'regex:'.Patterns::NAME],
            'email' => ['required', 'string', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => Passwords::rules(),
            // Only these two can be chosen at sign-up. An admin is made by another admin (or `php artisan actify:make-admin`).
            'role' => ['required', Rule::in([Role::Resident->value, Role::Volunteer->value])],
        ];
    }

    public function messages(): array
    {
        return [
            'username.regex' => 'The username may only contain letters, numbers and underscores.',
            'username.unique' => 'That username is already taken.',
            'email.unique' => 'That email is already used by another account.',
            'first_name.regex' => 'The first name '.Patterns::NAME_MESSAGE,
            'last_name.regex' => 'The last name '.Patterns::NAME_MESSAGE,
            'role.in' => 'Choose resident or volunteer.',
        ];
    }
}
