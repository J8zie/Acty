<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\RequiresConfirmation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

// Checks what a volunteer sends when finishing a task.
class CompleteTaskRequest extends FormRequest
{
    use RequiresConfirmation;

    // Only the volunteer working on the report may finish it.
    public function authorize()
    {

        return Gate::inspect('work', $this->route('report'));
    }

    // The rules the finish form must pass.
    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'min:3', 'max:1000'],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:'.config('actify.images.max_kb')],
            ...$this->confirmationRules(),
        ];
    }

    // Friendlier wording for some of the error messages.
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
