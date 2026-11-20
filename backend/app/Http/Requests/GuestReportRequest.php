<?php

namespace App\Http\Requests;

use App\Enums\AssistanceType;
use App\Enums\CaptureMethod;
use App\Enums\Urgency;
use App\Rules\CaptureTime;
use App\Rules\Patterns;
use App\Rules\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /reports/guest: a report filed WITHOUT an account.
 *
 * Because nobody is signed in, this form is the most guarded one: who they are, two pictures (the problem and a
 * selfie) that must have been taken just now, and an explicit consent. `consent` is this form's "are you sure".
 */
class GuestReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $image = ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:'.config('actify.images.max_kb')];
        $methods = Rule::in(CaptureMethod::values());

        return [
            // who
            'first_name' => ['required', 'string', 'min:2', 'max:60', 'regex:'.Patterns::NAME],
            'last_name' => ['required', 'string', 'min:2', 'max:60', 'regex:'.Patterns::NAME],
            'phone' => ['required', 'string', 'max:30', new PhoneNumber],
            'email' => ['nullable', 'string', 'email:rfc', 'max:255'],
            'reporter_barangay' => ['required', 'string', 'max:80'],

            // what and where (same as a normal report)
            'title' => ['required', 'string', 'min:5', 'max:120'],
            'description' => ['required', 'string', 'min:15', 'max:2000'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'assistance_type' => ['required', Rule::enum(AssistanceType::class)],
            'urgency' => ['required', Rule::enum(Urgency::class)],
            'barangay' => ['required', 'string', 'max:80'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],

            // proof
            'photo' => $image,
            'photo_capture_method' => ['required', $methods],
            'photo_captured_at' => ['required', new CaptureTime($this->input('photo_capture_method'))],
            'selfie' => $image,
            'selfie_capture_method' => ['required', $methods],
            'selfie_captured_at' => ['required', new CaptureTime($this->input('selfie_capture_method'))],
            'liveness_prompt' => ['required', 'string', 'max:120'],

            'consent' => ['required', 'accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.regex' => 'The first name '.Patterns::NAME_MESSAGE,
            'last_name.regex' => 'The last name '.Patterns::NAME_MESSAGE,
            'title.min' => 'Give the report a title of at least 5 characters.',
            'description.min' => 'Describe the problem in at least 15 characters so a volunteer understands it.',
            'photo.required' => 'A live photo of the problem is needed.',
            'selfie.required' => 'A live selfie is needed.',
            'photo.mimes' => 'The photo must be a JPG, PNG or WebP file.',
            'selfie.mimes' => 'The selfie must be a JPG, PNG or WebP file.',
            'consent.required' => 'Please tick the box to agree before sending.',
            'consent.accepted' => 'Please tick the box to agree before sending.',
        ];
    }
}
