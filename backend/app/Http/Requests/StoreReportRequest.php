<?php

namespace App\Http\Requests;

use App\Enums\AssistanceType;
use App\Enums\Urgency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** POST /reports (a signed-in resident files a report). */
class StoreReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // `role:resident` on the route
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:5', 'max:120'],
            'description' => ['required', 'string', 'min:15', 'max:2000'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'assistance_type' => ['required', Rule::enum(AssistanceType::class)],
            'urgency' => ['required', Rule::enum(Urgency::class)],
            'barangay' => ['required', 'string', 'max:80'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:'.config('actify.images.max_kb')],
        ];
    }

    public function messages(): array
    {
        return [
            'title.min' => 'Give the report a title of at least 5 characters.',
            'description.min' => 'Describe the problem in at least 15 characters so a volunteer understands it.',
            'category_id.exists' => 'Choose one of the categories in the list.',
            'latitude.between' => 'The map position is not valid. Tap the map again.',
            'longitude.between' => 'The map position is not valid. Tap the map again.',
            'photo.mimes' => 'The photo must be a JPG, PNG or WebP file.',
            'photo.max' => 'The photo is too big (the limit is '.(int) (config('actify.images.max_kb') / 1024).' MB).',
        ];
    }
}
