<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RequiresConfirmation;
use Illuminate\Foundation\Http\FormRequest;

// Makes the person tick a confirm box before a dangerous action.
class ConfirmRequest extends FormRequest
{
    use RequiresConfirmation;

    // Anyone signed in may send this; the route checks the role.
    public function authorize(): bool
    {
        return true;
    }

    // Only asks for the confirm tick.
    public function rules(): array
    {
        return $this->confirmationRules();
    }

    // The confirm error messages.
    public function messages(): array
    {
        return $this->confirmationMessages();
    }
}
