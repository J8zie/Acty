<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

// Checks the picture file sent when changing a profile photo.
class UpdatePhotoRequest extends FormRequest
{
    // The signed-in person may upload their own photo.
    public function authorize(): bool
    {
        return true;
    }

    // The rules the photo file must pass.
    public function rules(): array
    {
        return [
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:'.config('actify.images.max_kb')],
        ];
    }

    // Friendlier wording for the photo errors.
    public function messages(): array
    {
        return [
            'photo.required' => 'Choose a picture first.',
            'photo.mimes' => 'The picture must be a JPG, PNG or WebP file.',
            'photo.max' => 'The picture is too big (the limit is '.(int) (config('actify.images.max_kb') / 1024).' MB).',
        ];
    }
}
