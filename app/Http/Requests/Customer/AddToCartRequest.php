<?php

namespace App\Http\Requests\Customer;

use App\Services\CustomerCart;
use App\Support\DemoData;
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

            foreach ($item['options'] as $group) {
                $picked = (array) ($this->input("options.{$group['code']}") ?? []);
                $valid = array_column($group['choices'], 'code');

                if (array_diff($picked, $valid)) {
                    $validator->errors()->add('options', "Choose a valid {$group['name']}.");
                } elseif ($group['type'] === 'single' && count($picked) > 1) {
                    $validator->errors()->add('options', "Choose only one {$group['name']}.");
                } elseif ($group['required'] && ! $picked) {
                    $validator->errors()->add('options', "Choose a {$group['name']}.");
                }
            }
        }];
    }

    /** @return array<string, string[]> option group code => choice codes */
    public function chosenOptions(): array
    {
        return array_map(fn ($picked) => (array) $picked, (array) $this->input('options', []));
    }
}
