<?php

namespace App\Http\Requests\Cashier;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Cancel (unpaid) or void (paid) an order — a reason is always required (document.md §3.4). */
class CancelOrderRequest extends FormRequest
{
    public const REASONS = [
        'Customer changed their mind',
        'Wrong item entered',
        'Item out of stock',
        'Duplicate order',
        'Customer left without paying',
    ];

    public function rules(): array
    {
        return [
            'reason' => ['required', Rule::in(self::REASONS)],
        ];
    }
}
