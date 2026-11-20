<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RequiresConfirmation;
use Illuminate\Foundation\Http\FormRequest;

/** A request whose only input is the confirmation: DELETE /reports/{id}, DELETE /admin/users/{id}. */
class ConfirmRequest extends FormRequest
{
    use RequiresConfirmation;

    public function authorize(): bool
    {
        return true; // who may do it is decided by the route's role middleware and the policy
    }

    public function rules(): array
    {
        return $this->confirmationRules();
    }

    public function messages(): array
    {
        return $this->confirmationMessages();
    }
}
