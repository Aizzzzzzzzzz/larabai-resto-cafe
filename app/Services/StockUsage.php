<?php

namespace App\Services;

/**
 * How much of each ingredient an order line uses (document.md §3.6 "Automatic deduction"):
 * the item's recipe, plus a 12 or 16 oz cup and a lid for drinks, plus what chosen options add or
 * swap (oat milk replaces fresh milk). Pure rules on the recipes; StockService uses them to deduct
 * and return stock.
 */
class StockUsage
{
    /**
     * @param  array<int, array{group: string, choice: string}>  $options  the line's chosen options
     * @return array<string, float> ingredient code => quantity used by the whole line
     */
    public static function forLine(string $itemCode, int $qty, array $options = []): array
    {
        $item = Catalog::item($itemCode);
        if (! $item) {
            return [];
        }

        $use = [];
        $add = function (string $code, float $amount) use (&$use) {
            $use[$code] = ($use[$code] ?? 0) + $amount;
        };

        foreach (Catalog::recipes()[$itemCode] ?? [] as [$ingredient, $amount]) {
            $add($ingredient, $amount);
        }

        if ($item['is_drink']) {
            $large = collect($options)->contains(fn ($o) => $o['group'] === 'size' && $o['choice'] === 'l');
            $add($large ? 'cup16' : 'cup12', 1);
            $add('lid', 1);
        }

        $optionRecipes = Catalog::optionRecipes()[$itemCode] ?? [];
        foreach ($options as $option) {
            $recipe = $optionRecipes[$option['group'].':'.$option['choice']] ?? null;
            if (! $recipe) {
                continue;
            }
            foreach ($recipe['add'] ?? [] as [$ingredient, $amount]) {
                $add($ingredient, $amount);
            }
            if (isset($recipe['replace']) && ! empty($use[$recipe['replace'][0]])) {
                [$from, $to] = $recipe['replace'];
                $add($to, $use[$from]); // oat milk takes over the fresh milk's quantity
                $use[$from] = 0;
            }
        }

        $out = [];
        foreach ($use as $code => $amount) {
            if ($total = round($amount * $qty, 2)) {
                $out[$code] = $total;
            }
        }

        return $out;
    }

    /**
     * @param  array<int, array{menu_item: string, qty: int, options: array}>  $lines
     * @return array<string, float>
     */
    public static function forOrder(array $lines): array
    {
        $total = [];
        foreach ($lines as $line) {
            foreach (self::forLine($line['menu_item'], $line['qty'], $line['options']) as $code => $amount) {
                $total[$code] = round(($total[$code] ?? 0) + $amount, 2);
            }
        }

        return $total;
    }

    /** Codes of the ingredients that can't cover one basic serving of the item. */
    public static function shortFor(array $item): array
    {
        $stock = array_column(Catalog::ingredients(), 'stock_qty', 'code');

        return array_keys(array_filter(
            self::forLine($item['code'], 1),
            fn ($needed, $code) => ($stock[$code] ?? 0) < $needed,
            ARRAY_FILTER_USE_BOTH,
        ));
    }

    /** Menu items whose recipe uses the ingredient. */
    public static function usedIn(string $ingredientCode): array
    {
        $recipes = Catalog::recipes();

        return array_values(array_filter(
            Catalog::menu(),
            fn ($item) => collect($recipes[$item['code']] ?? [])->contains(fn ($row) => $row[0] === $ingredientCode),
        ));
    }
}
