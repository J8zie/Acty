<?php

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** GET /admin/users?q=&role=&sort=&limit=&page= (the People page). */
class ListUsersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // `role:admin` on the route
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', Rule::enum(Role::class)],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'name', 'role'])],
            'limit' => ['nullable', 'integer', 'between:1,'.config('actify.pagination.max')],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ];
    }
}
