<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RequiresConfirmation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Checks the approve-or-reject choice an admin sends for a guest report.
class ReviewReportRequest extends FormRequest
{
    use RequiresConfirmation;

    // The route already limits this to admins.
    public function authorize(): bool
    {
        return true;
    }

    // The rules the review must pass.
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            ...$this->confirmationRules(),
        ];
    }

    // Friendlier wording for the review errors.
    public function messages(): array
    {
        return [
            'decision.required' => 'Choose approve or reject.',
            'decision.in' => 'Choose approve or reject.',
            ...$this->confirmationMessages(),
        ];
    }
}
