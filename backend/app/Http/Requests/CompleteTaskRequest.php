<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RequiresConfirmation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** POST /reports/{id}/complete (the volunteer who took the task says it is finished). */
class CompleteTaskRequest extends FormRequest
{
    use RequiresConfirmation;

    public function authorize()
    {
        // Only the volunteer who holds this report's task. Checked BEFORE the fields, so a stranger learns nothing.
        return Gate::inspect('work', $this->route('report'));
    }

    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'min:3', 'max:1000'],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:'.config('actify.images.max_kb')],
            ...$this->confirmationRules(),
        ];
    }

    public function messages(): array
    {
        return [
            'note.required' => 'Write a short note about what you did.',
            'note.min' => 'Write a short note about what you did.',
            'photo.mimes' => 'The photo must be a JPG, PNG or WebP file.',
            ...$this->confirmationMessages(),
        ];
    }
}
