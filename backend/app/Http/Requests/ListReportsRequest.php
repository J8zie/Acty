<?php

namespace App\Http\Requests;

use App\Enums\AssistanceType;
use App\Enums\ReportStatus;
use App\Enums\Urgency;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

// Checks the filters and paging sent when listing reports.
class ListReportsRequest extends FormRequest
{
    // Anyone may list reports.
    public function authorize(): bool
    {
        return true;
    }

    // The rules every filter must pass.
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', $this->csvOf(ReportStatus::values())],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'urgency' => ['nullable', 'string', $this->csvOf(Urgency::values())],
            'assistance_type' => ['nullable', 'string', $this->csvOf(AssistanceType::values())],
            'barangay' => ['nullable', 'string', 'max:80'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'sort' => ['nullable', Rule::in(['newest', 'oldest', 'urgency', 'status'])],
            'scope' => ['nullable', Rule::in(['mine', 'tasks'])],
            'review' => ['nullable', Rule::in(['pending'])],
            'limit' => ['nullable', 'integer', 'between:1,'.config('actify.pagination.max')],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ];
    }

    // Friendlier wording for the date errors.
    public function messages(): array
    {
        return [
            'to.after_or_equal' => 'The end date must be the same day as the start date or later.',
            'from.date_format' => 'Use the date format YYYY-MM-DD.',
            'to.date_format' => 'Use the date format YYYY-MM-DD.',
        ];
    }


    // Splits a comma-separated filter into a clean list.
    public function list(string $key): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->query($key, '')))));
    }


    // Makes a rule that only allows the given comma-separated values.
    private function csvOf(array $allowed): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($allowed): void {
            foreach (array_filter(array_map('trim', explode(',', (string) $value))) as $part) {
                if (! in_array($part, $allowed, true)) {
                    $fail("The {$attribute} \"{$part}\" is not valid. Use: ".implode(', ', $allowed).'.');

                    return;
                }
            }
        };
    }
}
