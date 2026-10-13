<?php

namespace App\Http\Requests\Inventory;

use App\Enums\Unit;
use App\Services\Catalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Add or edit an ingredient. The unit and opening stock are set when adding only: changing a unit
 * later would break recipes, and stock changes go through Restock so they leave a history.
 */
class IngredientRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        abort_if($this->route('ingredient') && ! Catalog::ingredient($this->route('ingredient')), 404);
    }

    public function rules(): array
    {
        $editing = $this->route('ingredient');

        return [
            'name' => ['required', 'string', 'max:100', function ($attribute, $value, $fail) use ($editing) {
                $taken = collect(Catalog::ingredients())
                    ->contains(fn ($i) => mb_strtolower($i['name']) === mb_strtolower(trim($value)) && $i['code'] !== $editing);
                if ($taken) {
                    $fail("{$value} already exists.");
                }
            }],
            'category' => ['required', 'string', 'max:50'],
            'unit' => $editing ? ['prohibited'] : ['required', Rule::enum(Unit::class)],
            'reorder_level' => ['required', 'numeric', 'min:0'],
            'restock_qty' => ['required', 'numeric', 'gt:0'],
            'unit_cost' => ['required', 'numeric', 'min:0'],
            'opening_stock' => $editing ? ['prohibited'] : ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Enter a name.',
            'category.required' => 'Enter a category.',
            'reorder_level.required' => 'Enter a reorder level.',
            'restock_qty.required' => 'Enter the usual restock quantity.',
            'restock_qty.gt' => 'Enter a restock quantity greater than zero.',
            'unit_cost.required' => 'Enter the cost per unit.',
            'unit.prohibited' => 'An ingredient’s unit can’t be changed.',
            'opening_stock.prohibited' => 'Use Restock to change the stock.',
        ];
    }
}
