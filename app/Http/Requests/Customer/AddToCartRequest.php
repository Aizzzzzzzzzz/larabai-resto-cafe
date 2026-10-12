<?php

namespace App\Http\Requests\Customer;

use App\Services\CustomerCart;
use App\Support\DemoData;
use App\Support\MenuOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** Item sheet "Add to order": item, options (checked against that item's option groups), note, quantity. */
class AddToCartRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'menu_item' => ['required', 'string'],
            'qty' => ['required', 'integer', 'min:1', 'max:'.CustomerCart::MAX_QTY],
            'notes' => ['nullable', 'string', 'max:120'],
            'options' => ['nullable', 'array'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $item = DemoData::item($this->input('menu_item'));
            if (! $item || ! $item['is_active']) {
                $validator->errors()->add('menu_item', 'That item is no longer on the menu.');

                return;
            }
            if (DemoData::isSoldOut($item)) {
                $validator->errors()->add('menu_item', "Sorry, {$item['name']} just sold out.");

                return;
            }

            if ($problem = MenuOptions::problem($item, $this->chosenOptions())) {
                $validator->errors()->add('options', $problem);
            }
        }];
    }

    /** @return array<string, string[]> option group code => choice codes */
    public function chosenOptions(): array
    {
        return array_map(fn ($picked) => (array) $picked, (array) $this->input('options', []));
    }
}
