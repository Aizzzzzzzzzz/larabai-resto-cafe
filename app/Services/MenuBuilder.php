<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\MenuItem;
use App\Models\OptionChoice;
use App\Models\OptionGroup;
use App\Support\OptionTemplates;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates and changes menu items together with their option groups and recipes, in one transaction,
 * so an item is never left half-made. The seeders and the admin's Menu & prices screen both use it.
 */
class MenuBuilder
{
    /**
     * @param  array{code?: string, category: string, name: string, description?: ?string, price: float|int, tone?: array, is_best_seller?: bool, is_new?: bool, sold_out_override?: ?bool, is_active?: bool}  $attributes  `category` is a category code
     * @param  array<int, array>  $groups  option groups in OptionTemplates format
     * @param  array<int, array{0: string, 1: float|int}>  $recipe  [[ingredient code, qty per serving], …]
     */
    public function create(array $attributes, array $groups, array $recipe): MenuItem
    {
        return DB::transaction(function () use ($attributes, $groups, $recipe) {
            $item = MenuItem::create([
                'code' => $attributes['code'] ?? $this->uniqueCode($attributes['name']),
                'category_id' => Category::where('code', $attributes['category'])->value('id'),
                'name' => $attributes['name'],
                'description' => $attributes['description'] ?? null,
                'price' => $attributes['price'],
                'tone' => $attributes['tone'] ?? ['#6E5240', '#D8C3AE'],
                'image_path' => $attributes['image_path'] ?? null,
                'is_best_seller' => $attributes['is_best_seller'] ?? false,
                'is_new' => $attributes['is_new'] ?? false,
                'sold_out_override' => $attributes['sold_out_override'] ?? null,
                'is_active' => $attributes['is_active'] ?? true,
            ]);

            $this->syncRecipe($item, $recipe);

            foreach ($groups as $position => $group) {
                $this->createGroup($item, $group, $position);
            }

            Catalog::flush();

            return $item;
        });
    }

    /**
     * Changes the details, the "Large adds" price of the size group and the recipe. Option groups of an
     * existing item stay as they are (document.md §3.7).
     */
    public function update(MenuItem $item, array $attributes, ?float $largeAdds, array $recipe): MenuItem
    {
        return DB::transaction(function () use ($item, $attributes, $largeAdds, $recipe) {
            $item->update([
                'category_id' => Category::where('code', $attributes['category'])->value('id'),
                'name' => $attributes['name'],
                'description' => $attributes['description'] ?? null,
                'price' => $attributes['price'],
                'is_best_seller' => $attributes['is_best_seller'] ?? false,
                'is_new' => $attributes['is_new'] ?? false,
            ] + (array_key_exists('image_path', $attributes) ? ['image_path' => $attributes['image_path']] : []));

            if ($largeAdds !== null) {
                $size = $item->optionGroups()->where('code', 'size')->first();
                $size?->choices()->where('code', 'l')->update(['extra_price' => $largeAdds]);
            }

            $this->syncRecipe($item, $recipe);
            Catalog::flush();

            return $item;
        });
    }

    /** Replaces the item's ingredients per serving. */
    public function syncRecipe(MenuItem $item, array $recipe): void
    {
        $ids = Ingredient::pluck('id', 'code');
        $item->ingredients()->sync(collect($recipe)->values()->mapWithKeys(
            fn ($row, $position) => [$ids[$row[0]] => ['qty_per_serving' => $row[1], 'position' => $position]],
        )->all());
    }

    private function createGroup(MenuItem $item, array $group, int $position): void
    {
        $created = OptionGroup::create([
            'menu_item_id' => $item->id,
            'code' => $group['code'],
            'name' => $group['name'],
            'selection_type' => $group['type'],
            'is_required' => $group['required'],
            'sort_order' => $position,
        ]);

        $ids = Ingredient::pluck('id', 'code');
        $recipes = OptionTemplates::recipes();

        foreach ($group['choices'] as $order => $choice) {
            $model = OptionChoice::create([
                'option_group_id' => $created->id,
                'code' => $choice['code'],
                'name' => $choice['name'],
                'extra_price' => $choice['price'],
                'is_default' => $choice['code'] === $group['default'],
                'sort_order' => $order,
            ]);

            $recipe = $recipes[$group['code'].':'.$choice['code']] ?? null;
            foreach ($recipe['add'] ?? [] as [$ingredient, $qty]) {
                $model->ingredients()->attach($ids[$ingredient], ['qty' => $qty, 'action' => 'add']);
            }
            if (isset($recipe['replace'])) {
                [$from, $to] = $recipe['replace'];
                $model->ingredients()->attach($ids[$to], ['qty' => null, 'action' => 'replace', 'replaces_id' => $ids[$from]]);
            }
        }
    }

    /** "Sea Salt Latte" → sea-salt-latte (and -2, -3… if that code is taken). */
    private function uniqueCode(string $name): string
    {
        $base = Str::slug($name) ?: 'item';
        $code = $base;

        for ($n = 2; MenuItem::where('code', $code)->exists(); $n++) {
            $code = $base.'-'.$n;
        }

        return $code;
    }
}
