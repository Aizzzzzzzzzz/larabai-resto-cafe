<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** The logo form: a new picture, or "go back to the built-in mark". */
class BrandingRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'logo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'], // a real picture, 2 MB at most (no SVG)
            'remove_logo' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'logo.image' => 'Choose a JPG, PNG or WebP picture.',
            'logo.mimes' => 'Choose a JPG, PNG or WebP picture.',
            'logo.max' => 'That picture is over 2 MB. Choose a smaller one.',
            'logo.uploaded' => 'The picture could not be uploaded. It may be over the upload limit of this server.',
        ];
    }
}
