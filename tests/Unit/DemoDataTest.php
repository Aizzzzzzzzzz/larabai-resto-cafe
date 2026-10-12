<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use App\Support\DemoData;
use Tests\TestCase;

/** The sample data must be internally consistent — it becomes the database seeders. */
class DemoDataTest extends TestCase
{
    public function test_matches_the_mockup_counts(): void
    {
        $this->assertCount(6, DemoData::categories());
        $this->assertCount(27, DemoData::items());
        $this->assertCount(41, DemoData::ingredients());
        $this->assertCount(5, DemoData::users());
        $this->assertCount(5, DemoData::tables());
    }

    public function test_every_item_has_a_recipe_that_uses_known_ingredients(): void
    {
        $ingredients = array_column(DemoData::ingredients(), 'code');
        $recipes = DemoData::recipes();

        foreach (DemoData::items() as $item) {
            $this->assertArrayHasKey($item['code'], $recipes, "{$item['code']} has no recipe");
        }
        foreach ($recipes as $item => $rows) {
            $this->assertNotNull(DemoData::item($item), "recipe for unknown item {$item}");
            foreach ($rows as [$ingredient, $qty]) {
                $this->assertContains($ingredient, $ingredients);
                $this->assertGreaterThan(0, $qty);
            }
        }
    }

    public function test_option_recipes_point_at_real_choices_and_ingredients(): void
    {
        $ingredients = array_column(DemoData::ingredients(), 'code');
        $choices = collect(DemoData::items())->flatMap(fn ($i) => collect($i['options'])
            ->flatMap(fn ($g) => collect($g['choices'])->map(fn ($c) => $g['code'].':'.$c['code'])))->unique()->all();

        foreach (DemoData::optionRecipes() as $key => $recipe) {
            $this->assertContains($key, $choices);
            foreach ($recipe['add'] ?? [] as [$ingredient]) {
                $this->assertContains($ingredient, $ingredients);
            }
            foreach ($recipe['replace'] ?? [] as $ingredient) {
                $this->assertContains($ingredient, $ingredients);
            }
        }
    }

    public function test_orders_add_up(): void
    {
        foreach (DemoData::orders() as $o) {
            $lines = array_sum(array_map(fn ($l) => $l['unit_price'] * $l['qty'], $o['items']));
            $this->assertEqualsWithDelta($lines, $o['subtotal'], 0.001, $o['order_no']);
            $this->assertEqualsWithDelta($o['subtotal'] - $o['discount_amount'] + $o['delivery_fee'], $o['total'], 0.001, $o['order_no']);
            $this->assertSame($o['order_type'] === 'delivery' ? 50.0 : 0.0, $o['delivery_fee']);
            $this->assertSame($o['order_type'] === 'dine-in', $o['table_no'] !== null);

            if ($o['payment']) {
                $this->assertSame($o['total'], $o['payment']['amount']);
            }
        }
    }

    public function test_senior_discount_applies_to_the_eligible_share_only(): void
    {
        // ₱510 shared by 2 diners, 1 senior: share ₱255 → ÷1.12 → ×0.80 → discount ₱72.86
        $order = DemoData::order(2);

        $this->assertSame(510.0, (float) $order['subtotal']);
        $this->assertSame(72.86, $order['discount']['amount']);
        $this->assertSame(437.14, $order['total']);
    }

    public function test_every_stage_has_a_sample_order(): void
    {
        $statuses = array_unique(array_column(DemoData::orders(), 'status'));

        foreach (OrderStatus::cases() as $status) {
            $this->assertContains($status->value, $statuses, "no sample order is {$status->value}");
        }
    }

    public function test_only_paid_orders_have_a_paid_payment(): void
    {
        foreach (DemoData::orders() as $o) {
            $this->assertSame($o['paid_at'] !== null, ($o['payment']['status'] ?? null) !== null, $o['order_no']);
        }
        $this->assertNull(DemoData::order(9)['paid_at'], 'cash on delivery is unpaid while cooking');
        $this->assertSame('VOIDED', DemoData::order(4)['payment']['status']);
    }

    public function test_open_orders_contain_no_sold_out_items(): void
    {
        foreach (DemoData::orders() as $o) {
            if (in_array($o['status'], ['awaiting_payment', 'paid'], true)) {
                foreach ($o['items'] as $line) {
                    $this->assertFalse(DemoData::isSoldOut(DemoData::item($line['menu_item'])), "{$o['order_no']} has sold-out {$line['name']}");
                }
            }
        }
    }

    public function test_finds_orders_by_id_or_public_token(): void
    {
        $this->assertSame(8, DemoData::order(8)['id']);
        $this->assertSame(8, DemoData::order('demo-0008')['id']);
        $this->assertNull(DemoData::order(99));
    }
}
