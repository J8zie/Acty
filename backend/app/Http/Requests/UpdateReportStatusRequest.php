<?php

namespace App\Http\Requests;

use App\Enums\ReportStatus;
use App\Http\Requests\Concerns\RequiresConfirmation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Checks the new status an admin sends for a report.
class UpdateReportStatusRequest extends FormRequest
{
    use RequiresConfirmation;

    // The route already limits this to admins.
    public function authorize(): bool
    {
        return true;
    }

    // The rules the status change must pass; cancelling needs a confirm tick.
    public function rules(): array
    {
        $cancelling = $this->input('status') === ReportStatus::Cancelled->value;

        return [
            'status' => ['required', Rule::enum(ReportStatus::class)],
            ...($cancelling ? $this->confirmationRules() : []),
        ];
    }

    // The confirm error messages.
    public function messages(): array
    {
        return $this->confirmationMessages();
    }
}
