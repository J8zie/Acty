<?php

namespace App\Http\Requests;

use App\Rules\Passwords;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Validator;

// Checks the current and new passwords sent when changing a password.
class UpdatePasswordRequest extends FormRequest
{
    // The signed-in person may change their own password.
    public function authorize(): bool
    {
        return true;
    }

    // The rules the password form must pass.
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', 'max:255'],
            'password' => [...Passwords::rules(), 'confirmed', 'different:current_password'],
        ];
    }

    // Friendlier wording for some of the error messages.
    public function messages(): array
    {
        return [
            'password.confirmed' => 'The two new passwords do not match.',
            'password.different' => 'The new password must be different from the current one.',
        ];
    }

    // Checks that the current password typed is actually right.
    public function after(): array
    {
        return [function (Validator $validator) {
            if (! $validator->errors()->has('current_password')
                && ! Hash::check((string) $this->input('current_password'), $this->user()->password)) {
                $validator->errors()->add('current_password', 'Your current password is not right.');
            }
        }];
    }
}
