<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Restock: "Add a delivery" (quantity received, more than zero → PURCHASE) or
 * "Set counted amount" (what's on the shelf, zero allowed → ADJUST).
 */
class RestockRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'mode' => ['required', Rule::in(['add', 'count'])],
            'qty' => ['required', 'numeric', $this->input('mode') === 'count' ? 'min:0' : 'gt:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        $add = $this->input('mode') !== 'count';

        return [
            'qty.required' => $add ? 'Enter how much arrived, more than zero.' : 'Enter the counted amount (0 if there is none).',
            'qty.numeric' => $add ? 'Enter how much arrived, more than zero.' : 'Enter the counted amount (0 if there is none).',
            'qty.gt' => 'Enter how much arrived, more than zero.',
            'qty.min' => 'Enter the counted amount (0 if there is none).',
        ];
    }
}
