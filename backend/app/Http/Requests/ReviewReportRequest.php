<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RequiresConfirmation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** POST /reports/{id}/review (admin approves or rejects a guest report). Both decisions are confirmed. */
class ReviewReportRequest extends FormRequest
{
    use RequiresConfirmation;

    public function authorize(): bool
    {
        return true; // `role:admin` on the route
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            ...$this->confirmationRules(),
        ];
    }

    public function messages(): array
    {
        return [
            'decision.required' => 'Choose approve or reject.',
            'decision.in' => 'Choose approve or reject.',
            ...$this->confirmationMessages(),
        ];
    }
}
