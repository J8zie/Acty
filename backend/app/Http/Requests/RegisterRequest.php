<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Rules\Passwords;
use App\Rules\Patterns;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Checks the details sent when someone signs up.
class RegisterRequest extends FormRequest
{
    // Anyone may try to register.
    public function authorize(): bool
    {
        return true;
    }

    // Tidies the username and email before they are checked.
    protected function prepareForValidation(): void
    {

        $this->merge([
            'username' => is_string($this->username) ? mb_strtolower(trim($this->username)) : $this->username,
            'email' => is_string($this->email) ? mb_strtolower(trim($this->email)) : $this->email,
        ]);
    }

    // The rules every field must pass.
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'min:3', 'max:30', 'regex:'.Patterns::USERNAME, 'unique:users,username'],
            'first_name' => ['required', 'string', 'min:2', 'max:60', 'regex:'.Patterns::NAME],
            'last_name' => ['required', 'string', 'min:2', 'max:60', 'regex:'.Patterns::NAME],
            'email' => ['required', 'string', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => Passwords::rules(),

            'role' => ['required', Rule::in([Role::Resident->value, Role::Volunteer->value])],
        ];
    }

    // Friendlier wording for some of the error messages.
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
