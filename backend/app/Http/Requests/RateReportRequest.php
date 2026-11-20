<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RequiresConfirmation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** POST /reports/{id}/rating (the resident who filed the report rates the volunteer, once). */
class RateReportRequest extends FormRequest
{
    use RequiresConfirmation;

    public function authorize()
    {
        return Gate::inspect('rate', $this->route('report'));
    }

    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,5'],
            ...$this->confirmationRules(),
        ];
    }

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
