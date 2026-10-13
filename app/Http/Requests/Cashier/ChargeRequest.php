<?php

namespace App\Http\Requests\Cashier;

use App\Services\Catalog;
use App\Support\MenuOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * POS "Charge": the whole ticket in one form. Only item codes, options, notes and quantities
 * are sent — the server prices everything again.
 */
class ChargeRequest extends FormRequest
{
    public const MAX_QTY = 50;

    public function rules(): array
    {
        return [
            'order_type' => ['required', Rule::in(['take-out', 'dine-in'])],
            'table_no' => ['nullable', 'required_if:order_type,dine-in', Rule::in(array_column(Catalog::tables(), 'table_no'))],
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_item' => ['required', 'string'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:'.self::MAX_QTY],
            'items.*.notes' => ['nullable', 'string', 'max:120'],
            'items.*.options' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Tap an item on the left to start an order.',
            'table_no.required_if' => 'Choose the table for this dine-in order.',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            foreach ($this->input('items') as $line) {
                $item = Catalog::item($line['menu_item']);

                $problem = match (true) {
                    ! $item || ! $item['is_active'] => 'An item on the ticket is no longer on the menu.',
                    Catalog::isSoldOut($item) => "{$item['name']} just sold out. Remove it from the ticket.",
                    default => MenuOptions::problem($item, array_map(fn ($c) => (array) $c, $line['options'] ?? [])),
                };

                if ($problem) {
                    $validator->errors()->add('items', $problem);

                    return;
                }
            }
        }];
    }
}
