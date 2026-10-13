<?php

namespace App\Support;

/**
 * The ready-made option sets a menu item can start from (Hot/Iced, size, sugar level, add-ons…) and what
 * each option uses from stock. The admin picks a template when adding an item, and the sample menu is
 * built from the same pieces, so every item's options are consistent.
 */
class OptionTemplates
{
    /** Category codes whose items are served in a cup with a lid. */
    public const DRINK_CATEGORIES = ['coffee', 'noncoffee', 'milktea', 'frappe'];

    /** Option-group templates the admin can attach to new menu items. */
    public static function all(): array
    {
        return [
            'coffee' => ['label' => 'Coffee drink (hot/iced, size, coffee add-ons)', 'groups' => [self::temp(), self::size(20), self::coffeeAddons()]],
            'icedcoffee' => ['label' => 'Iced coffee (size, coffee add-ons)', 'groups' => [self::size(20), self::coffeeAddons()]],
            'drink' => ['label' => 'Non-coffee drink (hot/iced, size, add-ons)', 'groups' => [self::temp(), self::size(20), self::drinkAddons()]],
            'milktea' => ['label' => 'Milk tea (size, sugar level, toppings)', 'groups' => [self::size(20), self::sugar(), self::teaAddons()]],
            'frappe' => ['label' => 'Frappe (size, add-ons)', 'groups' => [self::size(25), self::drinkAddons()]],
            'meal' => ['label' => 'Rice meal (extra rice, egg, atchara)', 'groups' => [self::mealAddons()]],
            'none' => ['label' => 'No options', 'groups' => []],
        ];
    }

    /**
     * What each option uses: "group:choice" => add [[ingredient, qty]] or replace [from, to].
     * Oat or almond milk takes over the fresh milk's quantity.
     */
    public static function recipes(): array
    {
        return [
            'addons:shot' => ['add' => [['beans', 9]]],
            'addons:oat' => ['replace' => ['milk', 'oat']],
            'addons:almond' => ['replace' => ['milk', 'almond']],
            'addons:caramel' => ['add' => [['caramel', 15]]],
            'addons:vanilla' => ['add' => [['vanilla', 15]]],
            'addons:whip' => ['add' => [['cream', 30]]],
            'addons:seasalt' => ['add' => [['cream', 30]]],
            'addons:pearl' => ['add' => [['pearls', 50]]],
            'addons:nata' => ['add' => [['nata', 40]]],
            'addons:pudding' => ['add' => [['pudding', 1]]],
            'addons:cheese' => ['add' => [['creamcheese', 30]]],
            'addons:rice' => ['add' => [['rice', 150]]],
            'addons:egg' => ['add' => [['egg', 1]]],
            'addons:atchara' => ['add' => [['atchara', 40]]],
            'side:fries' => ['add' => [['fries', 120], ['seasoning', 3]]],
        ];
    }

    // ---------------------------------------------------------------- Option groups

    public static function group(string $code, string $name, string $type, bool $required, array $choices, ?string $default = null): array
    {
        return [
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'required' => $required,
            'default' => $default ?? ($type === 'single' ? $choices[0][0] : null),
            'choices' => array_map(fn ($c) => ['code' => $c[0], 'name' => $c[1], 'price' => $c[2]], $choices),
        ];
    }

    public static function temp(): array
    {
        return self::group('temp', 'Temperature', 'single', true, [['hot', 'Hot', 0], ['iced', 'Iced', 0]]);
    }

    public static function size(int $large): array
    {
        return self::group('size', 'Size', 'single', true, [['m', 'Medium (12 oz)', 0], ['l', 'Large (16 oz)', $large]]);
    }

    public static function sugar(): array
    {
        return self::group('sugar', 'Sugar level', 'single', true, [['s0', '0%', 0], ['s25', '25%', 0], ['s50', '50%', 0], ['s75', '75%', 0], ['s100', '100%', 0]], 's100');
    }

    public static function coffeeAddons(): array
    {
        return self::group('addons', 'Add-ons', 'multi', false, [
            ['shot', 'Extra espresso shot', 35], ['oat', 'Oat milk', 40], ['almond', 'Almond milk', 35],
            ['caramel', 'Caramel drizzle', 20], ['vanilla', 'Vanilla syrup', 20], ['whip', 'Whipped cream', 20],
        ]);
    }

    public static function drinkAddons(): array
    {
        return self::group('addons', 'Add-ons', 'multi', false, [['oat', 'Oat milk', 40], ['whip', 'Whipped cream', 20], ['seasalt', 'Sea salt cream', 30]]);
    }

    public static function teaAddons(): array
    {
        return self::group('addons', 'Add-ons', 'multi', false, [['pearl', 'Black pearls', 20], ['nata', 'Nata de coco', 20], ['pudding', 'Egg pudding', 25], ['cheese', 'Cream cheese foam', 35]]);
    }

    public static function mealAddons(): array
    {
        return self::group('addons', 'Add-ons', 'multi', false, [['rice', 'Extra garlic rice', 25], ['egg', 'Extra fried egg', 20], ['atchara', 'Atchara', 15]]);
    }
}
