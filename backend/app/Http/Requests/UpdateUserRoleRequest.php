<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Http\Requests\Concerns\RequiresConfirmation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** PATCH /admin/users/{id}: change somebody's role. Powerful (a new admin can do everything), so it is confirmed. */
class UpdateUserRoleRequest extends FormRequest
{
    use RequiresConfirmation;

    public function authorize(): bool
    {
        return true; // `role:admin` on the route
    }

    public function rules(): array
    {
        return [
            'role' => ['required', Rule::enum(Role::class)],
            ...$this->confirmationRules(),
        ];
    }

    public function messages(): array
    {
        return $this->confirmationMessages();
    }
}
