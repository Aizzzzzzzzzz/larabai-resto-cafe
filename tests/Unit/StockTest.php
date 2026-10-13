<?php

namespace Tests\Unit;

use App\Enums\MovementType;
use App\Services\StockUsage;
use App\Support\DemoData;
use App\Support\DemoMovements;
use App\Support\StockLevel;
use Tests\TestCase;

/** Stock rules: what an order uses, how levels are named and shown, and the sample history. */
class StockTest extends TestCase
{
    public function test_a_drink_uses_its_recipe_a_cup_and_a_lid(): void
    {
        $this->assertSame(['beans' => 18.0, 'milk' => 180.0, 'cup12' => 1.0, 'lid' => 1.0], StockUsage::forLine('latte', 1));
        $this->assertSame(['beans' => 36.0, 'milk' => 360.0, 'cup16' => 2.0, 'lid' => 2.0], StockUsage::forLine('latte', 2, [['group' => 'size', 'choice' => 'l']]));
    }

    public function test_food_has_no_cup_or_lid(): void
    {
        $this->assertSame(['tapa' => 120.0, 'rice' => 150.0, 'egg' => 1.0, 'atchara' => 30.0], StockUsage::forLine('tapa', 1));
    }

    public function test_options_add_ingredients_or_swap_the_milk(): void
    {
        $oatLatte = StockUsage::forLine('latte', 2, [['group' => 'size', 'choice' => 'l'], ['group' => 'addons', 'choice' => 'oat'], ['group' => 'addons', 'choice' => 'shot']]);

        $this->assertEquals(['beans' => 54, 'oat' => 360, 'cup16' => 2, 'lid' => 2], $oatLatte); // 2 × (18 + 9) beans; no fresh milk

        // A side of fries adds fries and seasoning to the sandwich's own recipe
        $this->assertEquals(
            ['bread' => 3.0, 'chicken' => 60.0, 'ham' => 40.0, 'egg' => 1.0, 'cheese' => 2.0, 'fries' => 120.0, 'seasoning' => 3.0],
            StockUsage::forLine('clubhouse', 1, [['group' => 'side', 'choice' => 'fries']]),
        );
    }

    public function test_an_order_adds_up_its_lines(): void
    {
        $total = StockUsage::forOrder(DemoData::order(8)['items']); // 2× iced large oat latte + chicken adobo

        $this->assertSame(360.0, $total['oat']);
        $this->assertSame(2.0, $total['cup16']);
        $this->assertSame(150.0, $total['chicken']);
        $this->assertArrayNotHasKey('milk', $total);
    }

    public function test_short_items_and_where_an_ingredient_is_used(): void
    {
        $this->assertSame(['taro'], StockUsage::shortFor(DemoData::item('taro')));
        $this->assertSame([], StockUsage::shortFor(DemoData::item('latte')));
        $this->assertSame(['matcha', 'matchafrappe'], array_column(StockUsage::usedIn('matcha'), 'code'));
        $this->assertSame([], StockUsage::usedIn('oat')); // only through add-ons
    }

    public function test_levels(): void
    {
        $this->assertSame('out', StockLevel::of(['stock_qty' => 0, 'reorder_level' => 300]));
        $this->assertSame('low', StockLevel::of(['stock_qty' => 300, 'reorder_level' => 300]));
        $this->assertSame('ok', StockLevel::of(['stock_qty' => 301, 'reorder_level' => 300]));
        $this->assertSame('Out of stock', StockLevel::label('out'));
        $this->assertSame('chip-caramel', StockLevel::chip('low'));
    }

    public function test_quantities_are_shown_in_friendly_units(): void
    {
        $this->assertSame('12.4 kg', StockLevel::format(12400, 'g'));
        $this->assertSame('850 g', StockLevel::format(850, 'g'));
        $this->assertSame('18 L', StockLevel::format(18000, 'ml'));
        $this->assertSame('1 pc', StockLevel::format(1, 'pc'));
        $this->assertSame('12 pcs', StockLevel::format(12, 'pc'));
        $this->assertSame('-150 ml', StockLevel::format(-150, 'ml'));
        $this->assertSame('₱0.095/ml', StockLevel::costText(0.095, 'ml'));
        $this->assertSame('₱3.50/g', StockLevel::costText(3.5, 'g'));
    }

    public function test_every_sample_history_ends_at_the_ingredients_current_stock(): void
    {
        foreach (DemoMovements::all() as $code => $moves) {
            $this->assertSame(MovementType::Adjust, $moves[0]['type'], "{$code} starts with an opening count");
            $this->assertGreaterThanOrEqual(0, $moves[0]['qty'], "{$code} opening stock");

            $balance = 0.0;
            foreach ($moves as $move) {
                $this->assertEqualsWithDelta($balance, $move['qty_before'], 0.001, $code);
                $balance += $move['qty'];
                $this->assertEqualsWithDelta($balance, $move['qty_after'], 0.001, $code);
                $this->assertGreaterThanOrEqual(0, $move['qty_after'], "{$code} never goes negative");
            }
            $this->assertEqualsWithDelta(DemoData::ingredient($code)['stock_qty'], $balance, 0.001, $code);
        }
    }

    public function test_sample_history_follows_the_order_rules(): void
    {
        $refs = fn (string $code, MovementType $type) => array_column(array_filter(DemoMovements::all()[$code] ?? [], fn ($m) => $m['type'] === $type), 'reference');

        // Paid orders and cash-on-delivery orders used stock; cancelled and unpaid counter orders did not
        $this->assertContains(DemoData::order(8)['order_no'], $refs('oat', MovementType::Sale));
        $this->assertContains(DemoData::order(9)['order_no'], $refs('longganisa', MovementType::Sale)); // COD, in the kitchen
        $this->assertNotContains(DemoData::order(5)['order_no'], $refs('brownsugar', MovementType::Sale)); // cancelled before paying
        $this->assertNotContains(DemoData::order(13)['order_no'], $refs('ube', MovementType::Sale));      // unpaid counter cash

        // The voided Café Mocha gave its chocolate sauce back
        $this->assertSame([DemoData::order(4)['order_no']], $refs('choco', MovementType::Return));
    }
}
