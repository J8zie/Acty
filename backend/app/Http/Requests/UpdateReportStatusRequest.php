<?php

namespace App\Http\Requests;

use App\Enums\ReportStatus;
use App\Http\Requests\Concerns\RequiresConfirmation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** PATCH /reports/{id} (admin). Cancelling a report must be confirmed; the other changes are easy to undo. */
class UpdateReportStatusRequest extends FormRequest
{
    use RequiresConfirmation;

    public function authorize(): bool
    {
        return true; // `role:admin` on the route
    }

    public function rules(): array
    {
        $cancelling = $this->input('status') === ReportStatus::Cancelled->value;

        return [
            'status' => ['required', Rule::enum(ReportStatus::class)],
            ...($cancelling ? $this->confirmationRules() : []),
        ];
    }

    public function messages(): array
    {
        return $this->confirmationMessages();
    }
}
