<?php

namespace App\Services;

use App\Support\Num;
use Illuminate\Support\Facades\DB;

/**
 * The menu, the stock list, the tables and the staff list as plain arrays for the screens
 * (the same shapes the mockup's BB_DATA used), read from the database.
 *
 * One snapshot is read per request and shared, because "is this item sold out?" needs the recipes and the
 * stock of every ingredient. Anything that changes the menu, stock, tables or staff calls flush() (the
 * models do it on save/delete, the stock service after moving stock), so the next read is fresh.
 */
class Catalog
{
    private static ?array $snapshot = null;

    public static function flush(): void
    {
        self::$snapshot = null;
    }

    // ---------------------------------------------------------------- Menu

    /** @return array<int, array{code: string, name: string, icon: string, is_drink: bool, sort_order: int}> */
    public static function categories(): array
    {
        return self::snapshot()['categories'];
    }

    /** Every menu item, including ones taken off the menu. Each has `sold_out` worked out from stock. */
    public static function items(): array
    {
        return self::snapshot()['items'];
    }

    /** What customers and the POS can order: active items only. */
    public static function menu(): array
    {
        return array_values(array_filter(self::items(), fn ($item) => $item['is_active']));
    }

    public static function item(string $code): ?array
    {
        return self::snapshot()['itemsByCode'][$code] ?? null;
    }

    /**
     * Sold out = the kitchen's switch when set, otherwise "an ingredient can't cover one serving"
     * (the recipe plus a 12 oz cup and a lid for drinks).
     */
    public static function isSoldOut(array $item): bool
    {
        return (bool) ($item['sold_out'] ?? false);
    }

    // ---------------------------------------------------------------- Stock

    public static function ingredients(): array
    {
        return self::snapshot()['ingredients'];
    }

    public static function ingredient(string $code): ?array
    {
        return self::snapshot()['ingredientsByCode'][$code] ?? null;
    }

    /** Ingredients per serving (same at every size): item code => [[ingredient code, qty], …]. */
    public static function recipes(): array
    {
        return self::snapshot()['recipes'];
    }

    /**
     * What each option of each item uses: item code => "group:choice" => ['add' => [[ingredient, qty]]]
     * or ['replace' => [from, to]].
     */
    public static function optionRecipes(): array
    {
        return self::snapshot()['optionRecipes'];
    }

    // ---------------------------------------------------------------- People & places

    public static function tables(): array
    {
        return self::snapshot()['tables'];
    }

    /** Staff accounts (never the password). */
    public static function users(): array
    {
        return self::snapshot()['users'];
    }

    // ---------------------------------------------------------------- Loading

    private static function snapshot(): array
    {
        return self::$snapshot ??= self::load();
    }

    private static function load(): array
    {
        $categories = DB::table('categories')->orderBy('sort_order')->orderBy('id')->get();
        $categoryCodes = $categories->pluck('code', 'id');

        $ingredients = DB::table('ingredients')->orderBy('id')->get()->map(fn ($i) => [
            'id' => $i->id,
            'code' => $i->code,
            'name' => $i->name,
            'category' => $i->category,
            'unit' => $i->unit,
            'stock_qty' => Num::tidy($i->stock_qty),
            'reorder_level' => Num::tidy($i->reorder_level),
            'restock_qty' => Num::tidy($i->restock_qty),
            'unit_cost' => Num::tidy($i->unit_cost),
        ])->all();
        $ingredientCodes = array_column($ingredients, 'code', 'id');
        $stock = array_column($ingredients, 'stock_qty', 'code');

        // item code => [[ingredient code, qty], …]
        $recipes = [];
        $itemCodes = DB::table('menu_items')->pluck('code', 'id');
        foreach (DB::table('item_ingredients')->orderBy('menu_item_id')->orderBy('position')->get() as $row) {
            $recipes[$itemCodes[$row->menu_item_id]][] = [$ingredientCodes[$row->ingredient_id], Num::tidy($row->qty_per_serving)];
        }

        $groups = DB::table('option_groups')->orderBy('sort_order')->orderBy('id')->get()->groupBy('menu_item_id');
        $choices = DB::table('option_choices')->orderBy('sort_order')->orderBy('id')->get()->groupBy('option_group_id');

        // item code => "group:choice" => recipe
        $optionRecipes = [];
        $choiceKeys = [];
        foreach ($groups->flatten(1) as $group) {
            foreach ($choices[$group->id] ?? [] as $choice) {
                $choiceKeys[$choice->id] = [$itemCodes[$group->menu_item_id], $group->code.':'.$choice->code];
            }
        }
        foreach (DB::table('choice_ingredients')->get() as $row) {
            [$itemCode, $key] = $choiceKeys[$row->option_choice_id];
            if ($row->action === 'replace') {
                $optionRecipes[$itemCode][$key]['replace'] = [$ingredientCodes[$row->replaces_id], $ingredientCodes[$row->ingredient_id]];
            } else {
                $optionRecipes[$itemCode][$key]['add'][] = [$ingredientCodes[$row->ingredient_id], Num::tidy($row->qty)];
            }
        }

        $drinkCategories = $categories->where('is_drink', true)->pluck('code')->all();

        $items = DB::table('menu_items')->orderBy('id')->get()->map(function ($item) use ($categoryCodes, $drinkCategories, $groups, $choices, $recipes, $stock) {
            $category = $categoryCodes[$item->category_id];
            $isDrink = in_array($category, $drinkCategories, true);
            $override = $item->sold_out_override === null ? null : (bool) $item->sold_out_override;

            $options = [];
            foreach ($groups[$item->id] ?? [] as $group) {
                $rows = $choices[$group->id] ?? collect();
                $options[] = [
                    'code' => $group->code,
                    'name' => $group->name,
                    'type' => $group->selection_type,
                    'required' => (bool) $group->is_required,
                    'default' => $rows->firstWhere('is_default', true)->code ?? null,
                    'choices' => $rows->map(fn ($c) => ['code' => $c->code, 'name' => $c->name, 'price' => Num::tidy($c->extra_price)])->values()->all(),
                ];
            }

            return [
                'id' => $item->id,
                'code' => $item->code,
                'category' => $category,
                'name' => $item->name,
                'price' => Num::tidy($item->price),
                'description' => $item->description,
                'tone' => json_decode($item->tone, true),
                'is_best_seller' => (bool) $item->is_best_seller,
                'is_new' => (bool) $item->is_new,
                'is_drink' => $isDrink,
                'sold_out_override' => $override,
                'is_active' => (bool) $item->is_active,
                'options' => $options,
                'sold_out' => $override ?? self::lacksStock($recipes[$item->code] ?? [], $isDrink, $stock),
            ];
        })->all();

        return [
            'categories' => $categories->map(fn ($c) => [
                'id' => $c->id,
                'code' => $c->code,
                'name' => $c->name,
                'icon' => $c->icon,
                'is_drink' => (bool) $c->is_drink,
                'sort_order' => $c->sort_order,
            ])->all(),
            'items' => $items,
            'itemsByCode' => array_column($items, null, 'code'),
            'ingredients' => $ingredients,
            'ingredientsByCode' => array_column($ingredients, null, 'code'),
            'recipes' => $recipes,
            'optionRecipes' => $optionRecipes,
            'tables' => DB::table('dining_tables')->orderBy('table_no')->get()->map(fn ($t) => [
                'id' => $t->id,
                'table_no' => $t->table_no,
                'seats' => $t->seats,
                'is_active' => (bool) $t->is_active,
            ])->all(),
            'users' => DB::table('users')->orderBy('id')->get()->map(fn ($u) => [
                'id' => $u->id,
                'full_name' => $u->full_name,
                'username' => $u->username,
                'role' => $u->role,
                'is_active' => (bool) $u->is_active,
                'last_login_at' => $u->last_login_at,
            ])->all(),
        ];
    }

    /** Whether any ingredient of one basic serving (recipe, plus a 12 oz cup and a lid for drinks) is short. */
    private static function lacksStock(array $recipe, bool $isDrink, array $stock): bool
    {
        $needs = $isDrink ? [...$recipe, ['cup12', 1], ['lid', 1]] : $recipe;

        foreach ($needs as [$ingredient, $qty]) {
            if (($stock[$ingredient] ?? 0) < $qty) {
                return true;
            }
        }

        return false;
    }
}
