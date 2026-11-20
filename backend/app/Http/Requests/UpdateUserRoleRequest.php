<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Http\Requests\Concerns\RequiresConfirmation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Checks the new role an admin sends for a user.
class UpdateUserRoleRequest extends FormRequest
{
    use RequiresConfirmation;

    // The route already limits this to admins.
    public function authorize(): bool
    {
        return true;
    }

    // The rules the role change must pass.
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::enum(Role::class)],
            ...$this->confirmationRules(),
        ];
    }

    // The confirm error messages.
    public function messages(): array
    {
        return $this->confirmationMessages();
    }
}
