<?php

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Checks the search, filter and paging sent when an admin lists users.
class ListUsersRequest extends FormRequest
{
    // The route already limits this to admins.
    public function authorize(): bool
    {
        return true;
    }

    // The rules every filter must pass.
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
