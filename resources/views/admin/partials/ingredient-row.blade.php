{{--
    One "ingredient + quantity" row of the item editor. Used for the <template> JS clones (index __i__)
    and for the rows typed before a failed save. Names: ingredients[i][code], ingredients[i][qty].
--}}
@php
    $selected = $code ?: $firstIngredient;
    $unit = collect($ingredientGroups)->flatten(1)->firstWhere('code', $selected)['unit'] ?? '';
@endphp
<div class="ing-row">
    <select class="input" name="ingredients[{{ $i }}][code]" data-ing="code" aria-label="Ingredient">
        @foreach ($ingredientGroups as $group => $list)
            <optgroup label="{{ $group }}">
                @foreach ($list as $ingredient)
                    <option value="{{ $ingredient['code'] }}" data-unit="{{ $ingredient['unit'] }}" @selected($ingredient['code'] === $selected)>{{ $ingredient['name'] }}</option>
                @endforeach
            </optgroup>
        @endforeach
    </select>
    <span class="unit-input"><input class="input" name="ingredients[{{ $i }}][qty]" type="number" min="0" step="any" value="{{ $qty }}" aria-label="Quantity"><span data-ing="unit">{{ $unit }}</span></span>
    <button type="button" class="x-btn" data-ing="remove" aria-label="Remove ingredient">×</button>
</div>
