<?php

namespace App\Http\Requests;

use App\Enums\AssistanceType;
use App\Enums\ReportStatus;
use App\Enums\Urgency;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * GET /reports?... : every way the Reports page, the volunteer "Nearby" page and the admin table can narrow a list.
 *
 *   q             words to search for (all words must match the title, description, barangay or category; "#12" or "12" = report number)
 *   status        one status or several separated by commas: open,in_progress
 *   category_id   one category
 *   urgency       low | medium | high (commas for several)
 *   assistance_type   cleanup | repair | supplies | manpower | awareness | other (commas for several)
 *   barangay      exact barangay name
 *   from, to      created between two dates (YYYY-MM-DD, both included)
 *   sort          newest (default) | oldest | urgency | status
 *   scope         mine | tasks     (needs a token)
 *   review        pending          (admin: guest reports waiting for approval)
 *   limit, page   paging (default 100 per page, at most 200)
 */
class ListReportsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

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

    public function messages(): array
    {
        return [
            'to.after_or_equal' => 'The end date must be the same day as the start date or later.',
            'from.date_format' => 'Use the date format YYYY-MM-DD.',
            'to.date_format' => 'Use the date format YYYY-MM-DD.',
        ];
    }

    /** "open,in_progress" -> ['open', 'in_progress'] (empty when the filter is not used). */
    public function list(string $key): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->query($key, '')))));
    }

    /** A rule that accepts "a" or "a,b" when every part is one of the allowed values. */
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
