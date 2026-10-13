<?php

namespace App\Support;

use App\Enums\PaymentMethod;
use App\Services\Pricing;

/**
 * The mockup's sample data (larabai-mockup/js/app.js → BB_DATA), ported to PHP.
 *
 * UI scaffold: controllers pass these arrays to the views. The keys match the future model
 * attributes (development-plan.md §3), and Eloquent models support $model['key'] access too,
 * so views keep working when Phase 1+ swaps these arrays for queries. The same data then
 * becomes the database seeders.
 */
class DemoData
{
    // ---------------------------------------------------------------- Menu

    /** Category codes whose items are served in a cup with a lid. */
    public const DRINK_CATEGORIES = ['coffee', 'noncoffee', 'milktea', 'frappe'];

    public static function categories(): array
    {
        $rows = [
            ['coffee', 'Coffee', 'cup'],
            ['noncoffee', 'Non-coffee', 'cup'],
            ['milktea', 'Milk tea', 'tea'],
            ['frappe', 'Frappe', 'tea'],
            ['meals', 'Rice meals', 'bowl'],
            ['pastries', 'Pastries & snacks', 'bread'],
        ];

        return array_map(fn ($r, $i) => [
            'code' => $r[0],
            'name' => $r[1],
            'icon' => $r[2],
            'is_drink' => in_array($r[0], self::DRINK_CATEGORIES, true),
            'sort_order' => $i + 1,
        ], $rows, array_keys($rows));
    }

    public static function items(): array
    {
        [$temp, $sugar] = [self::temp(), self::sugar()];
        [$coffee, $drink, $tea, $meal] = [self::coffeeAddons(), self::drinkAddons(), self::teaAddons(), self::mealAddons()];

        // [code, category, name, price, description, tone, tags, option groups]
        $rows = [
            // Coffee
            ['americano', 'coffee', 'Americano', 110, 'Double espresso over hot or cold water. Bold and clean.', ['#4A2E1C', '#9C6B45'], [], [$temp, self::size(20), $coffee]],
            ['latte', 'coffee', 'Café Latte', 145, 'Espresso with steamed fresh milk and a thin layer of foam.', ['#7A4A26', '#C99467'], ['Best seller'], [$temp, self::size(20), $coffee]],
            ['spanish', 'coffee', 'Spanish Latte', 160, 'Espresso, fresh milk and condensed milk. Sweet and creamy.', ['#8A5530', '#E0B585'], ['Best seller'], [$temp, self::size(20), $coffee]],
            ['macchiato', 'coffee', 'Caramel Macchiato', 165, 'Vanilla milk marked with espresso and finished with caramel drizzle.', ['#B67A2C', '#F0CF8F'], [], [$temp, self::size(20), $coffee]],
            ['mocha', 'coffee', 'Café Mocha', 160, 'Espresso, dark chocolate sauce and steamed milk.', ['#3E2418', '#8B5A3C'], [], [$temp, self::size(20), $coffee]],
            ['seasalt', 'coffee', 'Sea Salt Latte', 170, 'Iced latte topped with our house sea salt cream.', ['#6E5240', '#D8C3AE'], ['New'], [self::size(20), $coffee]],

            // Non-coffee
            ['matcha', 'noncoffee', 'Matcha Latte', 165, 'Ceremonial-grade matcha whisked with milk.', ['#3F6B3A', '#A9C98A'], ['Best seller'], [$temp, self::size(20), $drink]],
            ['choco', 'noncoffee', 'Classic Chocolate', 140, 'Rich Davao cacao chocolate with steamed milk.', ['#4B2A20', '#9E6650'], [], [$temp, self::size(20), $drink]],
            ['strawberry', 'noncoffee', 'Strawberry Milk', 150, 'Baguio strawberry jam layered with cold fresh milk.', ['#B8475B', '#F2B5BF'], [], [self::size(20), $drink]],
            ['ube', 'noncoffee', 'Ube Latte', 155, 'Purple yam halaya blended with milk. No coffee.', ['#5B3A80', '#B79AD8'], ['New'], [$temp, self::size(20), $drink]],

            // Milk tea
            ['wintermelon', 'milktea', 'Wintermelon Milk Tea', 120, 'Black tea with wintermelon syrup and milk.', ['#9A7A4E', '#E3CFA6'], [], [self::size(20), $sugar, $tea]],
            ['okinawa', 'milktea', 'Okinawa Milk Tea', 130, 'Roasted brown sugar milk tea with a toffee finish.', ['#7C5434', '#CDA27A'], [], [self::size(20), $sugar, $tea]],
            ['brownsugar', 'milktea', 'Brown Sugar Pearl Milk', 140, 'Fresh milk with warm brown sugar pearls. Pearls included.', ['#5E3A1E', '#C8925C'], ['Best seller'], [self::size(20), $sugar, $tea]],
            ['taro', 'milktea', 'Taro Milk Tea', 130, 'Creamy taro root with jasmine tea.', ['#7E6A9E', '#CFC3E6'], [], [self::size(20), $sugar, $tea]],

            // Frappe
            ['javachip', 'frappe', 'Java Chip Frappe', 175, 'Coffee, chocolate chips and milk blended with ice.', ['#3A2418', '#7F5641'], [], [self::size(25), $coffee]],
            ['cookies', 'frappe', 'Cookies & Cream Frappe', 170, 'Crushed chocolate cookies blended with vanilla cream.', ['#2C2C2C', '#BDBDB5'], [], [self::size(25), $drink]],
            ['matchafrappe', 'frappe', 'Matcha Frappe', 180, 'Matcha blended with milk and ice, topped with cream.', ['#4E7A3E', '#C3DDA6'], [], [self::size(25), $drink]],

            // Rice meals
            ['tapa', 'meals', 'Beef Tapa', 199, 'House-cured beef tapa, garlic rice, fried egg and atchara.', ['#8A3B22', '#E09A63'], ['Best seller'], [$meal]],
            ['adobo', 'meals', 'Chicken Adobo Bowl', 189, 'Soy-vinegar braised chicken over garlic rice with a soft egg.', ['#5A3A22', '#B88A5A'], [], [$meal]],
            ['tocino', 'meals', 'Pork Tocino', 179, 'Sweet cured pork, garlic rice and fried egg.', ['#A8452F', '#F0A07E'], [], [$meal]],
            ['longganisa', 'meals', 'Garlic Longganisa', 179, 'Garlicky local sausage with garlic rice and fried egg.', ['#7B2F1F', '#D27B57'], [], [$meal]],
            ['spam', 'meals', 'Spam Silog', 169, 'Pan-fried Spam, garlic rice and fried egg.', ['#9C4A4A', '#EBA9A0'], [], [$meal]],

            // Pastries & snacks
            ['croissant', 'pastries', 'Butter Croissant', 95, 'Flaky all-butter croissant, baked every morning.', ['#B07A2E', '#F2D49B'], [], [
                self::group('warm', 'Serving', 'single', true, [['asis', 'As is', 0], ['warmed', 'Warmed', 0]]),
            ]],
            ['ensaymada', 'pastries', 'Ensaymada', 75, 'Soft brioche with butter, sugar and grated cheese.', ['#C49A3C', '#F6E2A8'], [], []],
            ['pandesal', 'pastries', 'Cheese Pandesal (4 pcs)', 85, 'Warm pandesal stuffed with melty cheese.', ['#A06A2C', '#E8C08A'], [], []],
            ['clubhouse', 'pastries', 'Clubhouse Sandwich', 195, 'Triple-decker with chicken, ham, egg and cheese.', ['#8C6A3A', '#DCC291'], [], [
                self::group('side', 'Add a side', 'multi', false, [['fries', 'Side of fries', 50]]),
            ]],
            ['fries', 'pastries', 'Flavored Fries', 110, 'Crispy shoestring fries tossed in your choice of flavor.', ['#C8902A', '#F7DB86'], [], [
                self::group('flavor', 'Flavor', 'single', true, [['cheese', 'Cheese', 0], ['bbq', 'Barbecue', 0], ['sour', 'Sour cream', 0]]),
            ]],
        ];

        return array_map(fn ($r) => [
            'code' => $r[0],
            'category' => $r[1],
            'name' => $r[2],
            'price' => $r[3],
            'description' => $r[4],
            'tone' => $r[5],
            'is_best_seller' => in_array('Best seller', $r[6], true),
            'is_new' => in_array('New', $r[6], true),
            'is_drink' => in_array($r[1], self::DRINK_CATEGORIES, true),
            'sold_out_override' => null,
            'is_active' => true,
            'options' => $r[7],
        ], $rows);
    }

    public static function item(string $code): ?array
    {
        return collect(self::items())->firstWhere('code', $code);
    }

    /**
     * Sold out = the kitchen's switch when set, otherwise "an ingredient can't cover one serving"
     * (recipe + a 12 oz cup and a lid for drinks), as in the mockup's menu.isSoldOut().
     */
    public static function isSoldOut(array $item): bool
    {
        if ($item['sold_out_override'] !== null) {
            return (bool) $item['sold_out_override'];
        }

        $stock = array_column(self::ingredients(), 'stock_qty', 'code');
        $needs = self::recipes()[$item['code']] ?? [];
        if ($item['is_drink']) {
            $needs = [...$needs, ['cup12', 1], ['lid', 1]];
        }

        foreach ($needs as [$ingredient, $qty]) {
            if (($stock[$ingredient] ?? 0) < $qty) {
                return true;
            }
        }

        return false;
    }

    /** Option-group templates the admin can attach to new menu items. */
    public static function optionTemplates(): array
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

    // ---------------------------------------------------------------- Inventory

    public static function ingredients(): array
    {
        // [code, name, category, unit, stock, reorder level, usual restock qty, cost per unit (₱)]
        $rows = [
            ['beans', 'Espresso beans', 'Coffee & tea', 'g', 6200, 3000, 5000, 1.2],
            ['blacktea', 'Black tea leaves', 'Coffee & tea', 'g', 800, 300, 1000, 0.8],
            ['matcha', 'Matcha powder', 'Coffee & tea', 'g', 650, 300, 1000, 3.5],
            ['milk', 'Fresh milk', 'Dairy', 'ml', 18000, 10000, 20000, 0.095],
            ['condensed', 'Condensed milk', 'Dairy', 'ml', 2400, 1000, 3000, 0.12],
            ['cream', 'Whipping cream', 'Dairy', 'ml', 900, 1000, 2000, 0.3],
            ['creamcheese', 'Cream cheese', 'Dairy', 'g', 350, 500, 1000, 0.6],
            ['cheese', 'Cheese slices', 'Dairy', 'pc', 40, 20, 50, 7],
            ['oat', 'Oat milk', 'Milk alternatives', 'ml', 1800, 4000, 6000, 0.22],
            ['almond', 'Almond milk', 'Milk alternatives', 'ml', 2500, 1000, 3000, 0.2],
            ['choco', 'Chocolate sauce', 'Syrups & sauces', 'ml', 2100, 800, 2000, 0.35],
            ['caramel', 'Caramel sauce', 'Syrups & sauces', 'ml', 1300, 500, 1500, 0.4],
            ['vanilla', 'Vanilla syrup', 'Syrups & sauces', 'ml', 1500, 500, 1500, 0.45],
            ['wintermelon', 'Wintermelon syrup', 'Syrups & sauces', 'ml', 2000, 800, 2000, 0.25],
            ['brownsugar', 'Brown sugar syrup', 'Syrups & sauces', 'ml', 3000, 1000, 3000, 0.15],
            ['ube', 'Ube halaya', 'Syrups & sauces', 'g', 1200, 500, 1000, 0.4],
            ['strawjam', 'Strawberry jam', 'Syrups & sauces', 'g', 900, 400, 1000, 0.35],
            ['taro', 'Taro powder', 'Milk tea add-ins', 'g', 0, 300, 1000, 0.6],
            ['pearls', 'Tapioca pearls', 'Milk tea add-ins', 'g', 4000, 2000, 5000, 0.12],
            ['nata', 'Nata de coco', 'Milk tea add-ins', 'g', 1500, 800, 2000, 0.15],
            ['pudding', 'Egg pudding cups', 'Milk tea add-ins', 'pc', 20, 12, 30, 12],
            ['frappebase', 'Frappe base powder', 'Milk tea add-ins', 'g', 2600, 1000, 3000, 0.5],
            ['cookies', 'Chocolate cookie crumbs', 'Milk tea add-ins', 'g', 1200, 500, 1000, 0.4],
            ['rice', 'Rice', 'Kitchen', 'g', 15000, 8000, 25000, 0.06],
            ['egg', 'Eggs', 'Kitchen', 'pc', 84, 60, 120, 9],
            ['tapa', 'Beef tapa', 'Kitchen', 'g', 3200, 2000, 5000, 0.55],
            ['chicken', 'Chicken', 'Kitchen', 'g', 4500, 2000, 5000, 0.28],
            ['tocino', 'Pork tocino', 'Kitchen', 'g', 2600, 1500, 4000, 0.4],
            ['longganisa', 'Garlic longganisa', 'Kitchen', 'pc', 45, 30, 60, 12],
            ['spam', 'Luncheon meat', 'Kitchen', 'g', 1800, 1000, 2000, 0.6],
            ['ham', 'Sliced ham', 'Kitchen', 'g', 1200, 500, 1000, 0.5],
            ['atchara', 'Atchara', 'Kitchen', 'g', 1400, 500, 1500, 0.15],
            ['fries', 'Frozen fries', 'Kitchen', 'g', 6000, 3000, 8000, 0.18],
            ['seasoning', 'Fries seasoning', 'Kitchen', 'g', 400, 150, 500, 0.5],
            ['croissant', 'Butter croissant', 'Bakery', 'pc', 14, 10, 24, 38],
            ['ensaymada', 'Ensaymada', 'Bakery', 'pc', 18, 10, 24, 28],
            ['pandesal', 'Cheese pandesal', 'Bakery', 'pc', 40, 24, 60, 9],
            ['bread', 'Sandwich bread slices', 'Bakery', 'pc', 30, 24, 60, 5],
            ['cup12', 'Cup 12 oz', 'Packaging', 'pc', 320, 200, 500, 4.5],
            ['cup16', 'Cup 16 oz', 'Packaging', 'pc', 180, 200, 500, 5.5],
            ['lid', 'Cup lids', 'Packaging', 'pc', 520, 300, 1000, 1.5],
        ];

        return array_map(fn ($r) => [
            'code' => $r[0],
            'name' => $r[1],
            'category' => $r[2],
            'unit' => $r[3],
            'stock_qty' => $r[4],
            'reorder_level' => $r[5],
            'restock_qty' => $r[6],
            'unit_cost' => $r[7],
        ], $rows);
    }

    public static function ingredient(string $code): ?array
    {
        return collect(self::ingredients())->firstWhere('code', $code);
    }

    /**
     * Ingredients per serving (same at every size): item code => [[ingredient code, qty], …].
     * Drinks also use one cup (12 or 16 oz by size) and one lid, added automatically.
     */
    public static function recipes(): array
    {
        return [
            'americano' => [['beans', 18]],
            'latte' => [['beans', 18], ['milk', 180]],
            'spanish' => [['beans', 18], ['milk', 150], ['condensed', 30]],
            'macchiato' => [['beans', 18], ['milk', 170], ['vanilla', 15], ['caramel', 10]],
            'mocha' => [['beans', 18], ['milk', 160], ['choco', 30]],
            'seasalt' => [['beans', 18], ['milk', 150], ['cream', 30]],
            'matcha' => [['matcha', 6], ['milk', 200]],
            'choco' => [['choco', 40], ['milk', 200]],
            'strawberry' => [['strawjam', 40], ['milk', 200]],
            'ube' => [['ube', 40], ['milk', 200]],
            'wintermelon' => [['blacktea', 5], ['wintermelon', 40], ['milk', 100]],
            'okinawa' => [['blacktea', 5], ['brownsugar', 40], ['milk', 120]],
            'brownsugar' => [['brownsugar', 40], ['milk', 200], ['pearls', 60]],
            'taro' => [['taro', 30], ['blacktea', 5], ['milk', 150]],
            'javachip' => [['beans', 18], ['frappebase', 30], ['choco', 20], ['milk', 150]],
            'cookies' => [['frappebase', 30], ['cookies', 25], ['milk', 150]],
            'matchafrappe' => [['matcha', 8], ['frappebase', 30], ['milk', 150]],
            'tapa' => [['tapa', 120], ['rice', 150], ['egg', 1], ['atchara', 30]],
            'adobo' => [['chicken', 150], ['rice', 150], ['egg', 1]],
            'tocino' => [['tocino', 120], ['rice', 150], ['egg', 1]],
            'longganisa' => [['longganisa', 3], ['rice', 150], ['egg', 1]],
            'spam' => [['spam', 100], ['rice', 150], ['egg', 1]],
            'croissant' => [['croissant', 1]],
            'ensaymada' => [['ensaymada', 1]],
            'pandesal' => [['pandesal', 4]],
            'clubhouse' => [['bread', 3], ['chicken', 60], ['ham', 40], ['egg', 1], ['cheese', 2]],
            'fries' => [['fries', 150], ['seasoning', 5]],
        ];
    }

    /** What each option uses: "group:choice" => add [[ingredient, qty]] or replace [from, to]. */
    public static function optionRecipes(): array
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

    // ---------------------------------------------------------------- People & places

    /** Staff accounts. Demo passwords are shown on the login screen (demo mode only). */
    public static function users(): array
    {
        $rows = [
            [1, 'Maria Dizon', 'admin', 'admin123', 'admin', 3],
            [2, 'Jessa Manalo', 'cashier', 'cashier123', 'cashier', 200],
            [3, 'Mark Bautista', 'cashier2', 'cashier123', 'cashier', 1500],
            [4, 'Ramon Cruz', 'kitchen', 'kitchen123', 'kitchen', 195],
            [5, 'Liza Garcia', 'stock', 'stock123', 'inventory', 2900],
        ];

        return array_map(fn ($r) => [
            'id' => $r[0],
            'full_name' => $r[1],
            'username' => $r[2],
            'password' => $r[3],
            'role' => $r[4],
            'is_active' => true,
            'last_login_at' => now()->subMinutes($r[5])->toDateTimeString(),
        ], $rows);
    }

    /** Five tables (document.md §1): two 2-seaters, two 4-seaters, one 6-seater. */
    public static function tables(): array
    {
        return [
            ['table_no' => 1, 'seats' => 2, 'is_active' => true],
            ['table_no' => 2, 'seats' => 2, 'is_active' => true],
            ['table_no' => 3, 'seats' => 4, 'is_active' => true],
            ['table_no' => 4, 'seats' => 4, 'is_active' => true],
            ['table_no' => 5, 'seats' => 6, 'is_active' => true],
        ];
    }

    // ---------------------------------------------------------------- Orders

    /**
     * Today's sample orders — one or more in every stage, so every screen and every
     * row action has something to show. Times are relative to now.
     */
    public static function orders(): array
    {
        $specs = [
            // n, source, type, table, status, method, placed (min ago), lines, extra
            [1, 'qr', 'dine-in', 2, 'completed', 'GCASH', 190, [['latte', 1, ['temp' => 'hot', 'size' => 'm']], ['croissant', 1, ['warm' => 'warmed']]], ['log' => ['preparing' => 186, 'ready' => 180, 'completed' => 176]]],
            [2, 'pos', 'take-out', null, 'completed', 'CASH', 170, [['spanish', 2, ['temp' => 'iced', 'size' => 'l']], ['ensaymada', 2, []]], ['tendered' => 1000, 'senior' => ['Senior', 'SC-2019-114523', 'Rosario Santos', 1, 2], 'log' => ['preparing' => 166, 'ready' => 160, 'completed' => 158]]],
            [3, 'qr', 'delivery', null, 'completed', 'MAYA', 150, [['tapa', 1, ['addons' => ['rice']]], ['matcha', 1, ['temp' => 'iced', 'size' => 'l']]], ['address' => 'Blk 4 Lot 12, Maliwalo, Tarlac City', 'landmark' => 'Blue gate near the chapel', 'log' => ['preparing' => 146, 'ready' => 128, 'completed' => 105]]],
            [4, 'pos', 'take-out', null, 'voided', 'CASH', 120, [['mocha', 1, ['temp' => 'hot', 'size' => 'm']]], ['tendered' => 200, 'reason' => 'Customer changed their mind', 'log' => ['preparing' => 118, 'voided' => 117]]],
            [5, 'qr', 'dine-in', 1, 'cancelled', 'CASH', 95, [['okinawa', 1, ['size' => 'm', 'sugar' => 's50', 'addons' => ['pearl']]]], ['reason' => 'Cancelled by customer', 'log' => ['cancelled' => 92]]],
            [6, 'qr', 'dine-in', 4, 'ready', 'GCASH', 32, [['brownsugar', 2, ['size' => 'l', 'sugar' => 's75']], ['clubhouse', 1, ['side' => ['fries']]]], ['notes' => [2 => 'Cut in half please'], 'log' => ['preparing' => 28, 'ready' => 9]]],
            [7, 'pos', 'take-out', null, 'ready', 'CASH', 27, [['americano', 1, ['temp' => 'iced', 'size' => 'l', 'addons' => ['shot']]]], ['tendered' => 200, 'log' => ['preparing' => 25, 'ready' => 6]]],
            [8, 'qr', 'dine-in', 3, 'preparing', 'GCASH', 18, [['latte', 2, ['temp' => 'iced', 'size' => 'l', 'addons' => ['oat']]], ['adobo', 1, []]], ['notes' => [1 => 'Less ice'], 'log' => ['preparing' => 12]]],
            [9, 'qr', 'delivery', null, 'preparing', 'CASH', 15, [['longganisa', 2, ['addons' => ['egg']]], ['wintermelon', 2, ['size' => 'm', 'sugar' => 's50']]], ['address' => '27 Mabini St., San Roque, Tarlac City', 'landmark' => null, 'log' => ['preparing' => 10]]],
            [10, 'qr', 'dine-in', 1, 'paid', 'MAYA', 7, [['macchiato', 1, ['temp' => 'hot', 'size' => 'm', 'addons' => ['whip']]], ['pandesal', 1, []]], []],
            [11, 'qr', 'delivery', null, 'awaiting_payment', 'CASH', 5, [['tocino', 1, []], ['javachip', 1, ['size' => 'l']]], ['address' => 'Unit 3B, Sto. Cristo Apartments, Tarlac City', 'landmark' => 'Beside the 7-Eleven']], // cash on delivery
            [12, 'qr', 'dine-in', 5, 'awaiting_payment', 'CASH', 4, [['okinawa', 3, ['size' => 'm', 'sugar' => 's100', 'addons' => ['pudding']]], ['fries', 2, ['flavor' => 'cheese']]], []],
            [13, 'qr', 'take-out', null, 'awaiting_payment', 'CASH', 2, [['ube', 1, ['temp' => 'iced', 'size' => 'm']]], []],
        ];

        return array_map(fn ($s) => self::buildOrder(...$s), $specs);
    }

    /** One sample order by id or public token. */
    public static function order(int|string $key): ?array
    {
        return collect(self::orders())->first(fn ($o) => (string) $o['id'] === (string) $key || $o['public_token'] === $key);
    }

    // ---------------------------------------------------------------- Builders

    private static function buildOrder(int $n, string $source, string $type, ?int $table, string $status, string $method, int $placedAgo, array $lineSpecs, array $extra): array
    {
        $date = now()->format('Ymd');
        $placedAt = now()->subMinutes($placedAgo);
        $cashier = $source === 'pos' ? 'Jessa Manalo' : null;
        $isCod = $type === 'delivery' && $method === 'CASH';

        $lines = [];
        foreach ($lineSpecs as $i => [$code, $qty, $chosen]) {
            $lines[] = self::buildLine($code, $qty, $chosen, $extra['notes'][$i + 1] ?? null);
        }

        $subtotal = round(array_sum(array_map(fn ($l) => $l['unit_price'] * $l['qty'], $lines)), 2);

        $discount = null;
        if (isset($extra['senior'])) {
            [$kind, $idNo, $holder, $eligible, $diners] = $extra['senior'];
            $discount = [
                'discount_type' => $kind,
                'id_no' => $idNo,
                'holder_name' => $holder,
                'eligible_count' => $eligible,
                'diner_count' => $diners,
                'amount' => Pricing::seniorDiscount($subtotal, $eligible, $diners),
            ];
        }

        $deliveryFee = $type === 'delivery' ? (float) config('cafe.delivery.fee') : 0.0;
        $total = Pricing::total($subtotal, $discount['amount'] ?? 0, $deliveryFee);

        // Paid unless still waiting at the counter, cancelled before paying, or cash on delivery not yet collected
        $paid = ! in_array($status, ['awaiting_payment', 'cancelled'], true) && ! ($isCod && $status !== 'completed');
        $paidAt = $paid ? $placedAt->copy()->addMinute() : null;

        $log = [['status' => 'awaiting_payment', 'at' => $placedAt->toDateTimeString(), 'by' => $cashier]];
        if ($paidAt) {
            $log[] = ['status' => 'paid', 'at' => $paidAt->toDateTimeString(), 'by' => $cashier];
        }
        foreach ($extra['log'] ?? [] as $logStatus => $ago) {
            $log[] = ['status' => $logStatus, 'at' => now()->subMinutes($ago)->toDateTimeString(), 'by' => match ($logStatus) {
                'preparing', 'ready' => 'Ramon Cruz',
                default => $source === 'pos' || $logStatus !== 'cancelled' ? 'Jessa Manalo' : null,
            }];
        }

        $payment = null;
        if ($paidAt || $status === 'voided') {
            $tendered = $method === 'CASH' ? ($extra['tendered'] ?? null) : null;
            $payment = [
                'method' => $method,
                'amount' => $total,
                'reference_no' => PaymentMethod::from($method)->prefix().'-'.$date.'-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT),
                'status' => $status === 'voided' ? 'VOIDED' : 'PAID',
                'amount_tendered' => $tendered,
                'change_due' => $tendered ? round($tendered - $total, 2) : null,
                'received_by' => $method === 'CASH' ? 'Jessa Manalo' : null,
                'paid_at' => $paidAt?->toDateTimeString(),
                'voided_at' => $status === 'voided' ? end($log)['at'] : null,
                'void_reason' => $status === 'voided' ? $extra['reason'] : null,
            ];
        }

        return [
            'id' => $n,
            'order_no' => 'BB-'.$date.'-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT),
            'public_token' => 'demo-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT),
            'source' => $source,
            'order_type' => $type,
            'table_no' => $table,
            'delivery_address' => $extra['address'] ?? null,
            'delivery_landmark' => $extra['landmark'] ?? null,
            'subtotal' => $subtotal,
            'discount_amount' => $discount['amount'] ?? 0,
            'delivery_fee' => $deliveryFee,
            'total' => $total,
            'payment_method' => $method,
            'status' => $status,
            'cancel_reason' => in_array($status, ['cancelled', 'voided'], true) ? $extra['reason'] : null,
            'is_cod' => $isCod,
            'created_by' => $cashier,
            'created_at' => $placedAt->toDateTimeString(),
            'paid_at' => $paidAt?->toDateTimeString(),
            'items' => $lines,
            'status_log' => $log,
            'payment' => $payment,
            'discount' => $discount,
        ];
    }

    /** @param  array<string, string|string[]>  $chosen  group code => choice code(s) */
    private static function buildLine(string $code, int $qty, array $chosen, ?string $notes): array
    {
        $item = self::item($code);
        $options = [];

        foreach ($item['options'] as $group) {
            foreach ((array) ($chosen[$group['code']] ?? []) as $choiceCode) {
                $choice = collect($group['choices'])->firstWhere('code', $choiceCode);
                $options[] = ['group' => $group['code'], 'choice' => $choice['code'], 'name' => $choice['name'], 'price' => $choice['price']];
            }
        }

        return [
            'menu_item' => $code,
            'name' => $item['name'],
            'qty' => $qty,
            'unit_price' => $item['price'] + array_sum(array_column($options, 'price')),
            'notes' => $notes,
            'options' => $options,
        ];
    }

    // ---------------------------------------------------------------- Option groups

    private static function group(string $code, string $name, string $type, bool $required, array $choices, ?string $default = null): array
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

    private static function temp(): array
    {
        return self::group('temp', 'Temperature', 'single', true, [['hot', 'Hot', 0], ['iced', 'Iced', 0]]);
    }

    private static function size(int $large): array
    {
        return self::group('size', 'Size', 'single', true, [['m', 'Medium (12 oz)', 0], ['l', 'Large (16 oz)', $large]]);
    }

    private static function sugar(): array
    {
        return self::group('sugar', 'Sugar level', 'single', true, [['s0', '0%', 0], ['s25', '25%', 0], ['s50', '50%', 0], ['s75', '75%', 0], ['s100', '100%', 0]], 's100');
    }

    private static function coffeeAddons(): array
    {
        return self::group('addons', 'Add-ons', 'multi', false, [
            ['shot', 'Extra espresso shot', 35], ['oat', 'Oat milk', 40], ['almond', 'Almond milk', 35],
            ['caramel', 'Caramel drizzle', 20], ['vanilla', 'Vanilla syrup', 20], ['whip', 'Whipped cream', 20],
        ]);
    }

    private static function drinkAddons(): array
    {
        return self::group('addons', 'Add-ons', 'multi', false, [['oat', 'Oat milk', 40], ['whip', 'Whipped cream', 20], ['seasalt', 'Sea salt cream', 30]]);
    }

    private static function teaAddons(): array
    {
        return self::group('addons', 'Add-ons', 'multi', false, [['pearl', 'Black pearls', 20], ['nata', 'Nata de coco', 20], ['pudding', 'Egg pudding', 25], ['cheese', 'Cream cheese foam', 35]]);
    }

    private static function mealAddons(): array
    {
        return self::group('addons', 'Add-ons', 'multi', false, [['rice', 'Extra garlic rice', 25], ['egg', 'Extra fried egg', 20], ['atchara', 'Atchara', 15]]);
    }
}
