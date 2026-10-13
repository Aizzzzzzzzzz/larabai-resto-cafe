<?php

namespace App\Http\Requests\Admin;

use App\Services\Catalog;
use App\Support\OptionTemplates;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Add or edit a menu item with the ingredients it uses per serving (document.md §3.7).
 * The option set is chosen from a template when adding; an existing item keeps its options,
 * and only the size group's "Large adds ₱" can change.
 */
class MenuItemRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        abort_if($this->route('menuItem') && ! (Catalog::item($this->route('menuItem'))['is_active'] ?? false), 404); // a missing item is a 404, not a validation error
    }

    public function rules(): array
    {
        $editing = $this->route('menuItem');

        return [
            'name' => ['required', 'string', 'max:100', function ($attribute, $value, $fail) use ($editing) {
                $taken = collect(Catalog::items())
                    ->contains(fn ($i) => mb_strtolower($i['name']) === mb_strtolower(trim($value)) && $i['code'] !== $editing);
                if ($taken) {
                    $fail("{$value} is already on the menu.");
                }
            }],
            'category' => ['required', Rule::in(array_column(Catalog::categories(), 'code'))],
            'price' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string', 'max:255'],
            'large' => ['nullable', 'numeric', 'min:0'],
            'template' => $editing ? ['prohibited'] : ['required', Rule::in(array_keys(OptionTemplates::all()))],
            'is_best_seller' => ['boolean'],
            'is_new' => ['boolean'],
            'ingredients' => ['required', 'array', 'min:1'],
            'ingredients.*.code' => ['required', Rule::in(array_column(Catalog::ingredients(), 'code'))],
            'ingredients.*.qty' => ['required', 'numeric', 'gt:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Enter the item name.',
            'price.required' => 'Enter a price greater than zero.',
            'price.gt' => 'Enter a price greater than zero.',
            'price.numeric' => 'Enter a price greater than zero.',
            'ingredients.required' => 'Add at least one ingredient so selling it deducts from Stock.',
            'ingredients.min' => 'Add at least one ingredient so selling it deducts from Stock.',
            'ingredients.*.qty.required' => 'Every ingredient needs a quantity greater than zero.',
            'ingredients.*.qty.gt' => 'Every ingredient needs a quantity greater than zero.',
            'ingredients.*.qty.numeric' => 'Every ingredient needs a quantity greater than zero.',
            'ingredients.*.code.in' => 'Choose an ingredient from the list.',
            'ingredients.*.code.required' => 'Choose an ingredient from the list.',
            'template.prohibited' => 'An existing item keeps its options.',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $codes = array_column($this->input('ingredients'), 'code');
            if (count($codes) !== count(array_unique($codes))) {
                $validator->errors()->add('ingredients', 'An ingredient is listed twice. Combine them into one row.');
            }

            if ($this->hasSize() && $this->input('large') === null) {
                $validator->errors()->add('large', 'Enter how much Large adds (0 if the same price).');
            }
        }];
    }

    /** The ingredients per serving as [[code, qty], …]. */
    public function recipe(): array
    {
        return array_map(fn ($row) => [$row['code'], (float) $row['qty']], array_values($this->input('ingredients', [])));
    }

    /** Whether the item has a Medium / Large choice, so "Large adds" applies. */
    private function hasSize(): bool
    {
        $groups = $this->route('menuItem')
            ? (Catalog::item($this->route('menuItem'))['options'] ?? [])
            : (OptionTemplates::all()[$this->input('template')]['groups'] ?? []);

        return collect($groups)->contains(fn ($g) => $g['code'] === 'size');
    }
}
