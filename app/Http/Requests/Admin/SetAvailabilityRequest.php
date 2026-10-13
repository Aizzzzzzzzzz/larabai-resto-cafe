<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Menu availability: auto (follows stock), always available, or sold out. */
class SetAvailabilityRequest extends FormRequest
{
    public function rules(): array
    {
        return ['availability' => ['required', Rule::in(['auto', 'on', 'off'])]];
    }
}
