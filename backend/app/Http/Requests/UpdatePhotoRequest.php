<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:'.config('actify.images.max_kb')],
        ];
    }

    public function messages(): array
    {
        return [
            'photo.required' => 'Choose a picture first.',
            'photo.mimes' => 'The picture must be a JPG, PNG or WebP file.',
            'photo.max' => 'The picture is too big (the limit is '.(int) (config('actify.images.max_kb') / 1024).' MB).',
        ];
    }
}
