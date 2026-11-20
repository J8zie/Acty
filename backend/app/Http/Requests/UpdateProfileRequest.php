<?php

namespace App\Http\Requests;

use App\Rules\Patterns;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * PATCH /auth/me. Changing the EMAIL is sensitive (it is how an account is recovered), so it also needs the
 * current password: a stolen, still-logged-in browser cannot silently take over the account.
 */
class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->email)) {
            $this->merge(['email' => mb_strtolower(trim($this->email))]);
        }
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'min:2', 'max:60', 'regex:'.Patterns::NAME],
            'last_name' => ['required', 'string', 'min:2', 'max:60', 'regex:'.Patterns::NAME],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($this->user()->id)],
            'current_password' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'That email is already used by another account.',
            'first_name.regex' => 'The first name '.Patterns::NAME_MESSAGE,
            'last_name.regex' => 'The last name '.Patterns::NAME_MESSAGE,
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $user = $this->user();
            $changed = is_string($this->email) && mb_strtolower($this->email) !== mb_strtolower($user->email);

            if (! $changed || $validator->errors()->has('email')) {
                return;
            }

            if (! $this->filled('current_password')) {
                $validator->errors()->add('current_password', 'Enter your current password to change your email.');
            } elseif (! Hash::check((string) $this->input('current_password'), $user->password)) {
                $validator->errors()->add('current_password', 'Your current password is not right.');
            }
        }];
    }
}
