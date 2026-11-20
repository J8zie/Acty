<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RequiresConfirmation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

// Checks the star rating a resident sends for a finished task.
class RateReportRequest extends FormRequest
{
    use RequiresConfirmation;

    // Only the person who filed the report may rate it.
    public function authorize()
    {
        return Gate::inspect('rate', $this->route('report'));
    }

    // The rules the rating must pass.
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,5'],
            ...$this->confirmationRules(),
        ];
    }

    // Friendlier wording for the rating errors.
    public function messages(): array
    {
        return [
            'rating.required' => 'Choose 1 to 5 stars.',
            'rating.integer' => 'Choose 1 to 5 stars.',
            'rating.between' => 'Choose 1 to 5 stars.',
            ...$this->confirmationMessages(),
        ];
    }
}
