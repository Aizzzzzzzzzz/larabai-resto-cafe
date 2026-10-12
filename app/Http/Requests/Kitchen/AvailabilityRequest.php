<?php

namespace App\Http\Requests\Kitchen;

use Illuminate\Foundation\Http\FormRequest;

/** Sold-out switch: available = 1 puts the item back on the menu, 0 marks it sold out. */
class AvailabilityRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'available' => ['required', 'boolean'],
        ];
    }
}
