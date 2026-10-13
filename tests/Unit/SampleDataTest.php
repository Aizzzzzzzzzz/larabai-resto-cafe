<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use App\Services\Catalog;
use App\Services\Orders;
use App\Support\OptionTemplates;
use Database\Seeders\Data\SampleData;
use Tests\TestCase;

/** The sample data must be internally consistent — the seeders turn it into the database — and the database must hold all of it. */
class SampleDataTest extends TestCase
{
    public function test_matches_the_mockup_counts(): void
    {
        $this->assertCount(6, SampleData::categories());
        $this->assertCount(27, SampleData::items());
        $this->assertCount(41, SampleData::ingredients());
        $this->assertCount(5, SampleData::users());
        $this->assertCount(5, SampleData::tables());
    }

    public function test_every_item_has_a_recipe_that_uses_known_ingredients(): void
    {
        $ingredients = array_column(SampleData::ingredients(), 'code');
        $recipes = SampleData::recipes();

        foreach (SampleData::items() as $item) {
            $this->assertArrayHasKey($item['code'], $recipes, "{$item['code']} has no recipe");
        }
        foreach ($recipes as $item => $rows) {
            $this->assertContains($item, array_column(SampleData::items(), 'code'), "recipe for unknown item {$item}");
            foreach ($rows as [$ingredient, $qty]) {
                $this->assertContains($ingredient, $ingredients);
                $this->assertGreaterThan(0, $qty);
            }
        }
    }

    public function test_option_recipes_point_at_real_choices_and_ingredients(): void
    {
        $ingredients = array_column(SampleData::ingredients(), 'code');
        $choices = collect(SampleData::items())->flatMap(fn ($i) => collect($i['options'])
            ->flatMap(fn ($g) => collect($g['choices'])->map(fn ($c) => $g['code'].':'.$c['code'])))->unique()->all();

        foreach (OptionTemplates::recipes() as $key => $recipe) {
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
        foreach (SampleData::orders() as $o) {
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
        $order = SampleData::order(2);

        $this->assertSame(510.0, (float) $order['subtotal']);
        $this->assertSame(72.86, $order['discount']['amount']);
        $this->assertSame(437.14, $order['total']);
    }

    public function test_every_stage_has_a_sample_order(): void
    {
        $statuses = array_unique(array_column(SampleData::orders(), 'status'));

        foreach (OrderStatus::cases() as $status) {
            $this->assertContains($status->value, $statuses, "no sample order is {$status->value}");
        }
    }

    public function test_only_paid_orders_have_a_paid_payment(): void
    {
        foreach (SampleData::orders() as $o) {
            $this->assertSame($o['paid_at'] !== null, ($o['payment']['status'] ?? null) !== null, $o['order_no']);
        }
        $this->assertNull(SampleData::order(9)['paid_at'], 'cash on delivery is unpaid while cooking');
        $this->assertSame('VOIDED', SampleData::order(4)['payment']['status']);
    }

    public function test_open_orders_contain_no_sold_out_items(): void
    {
        foreach (SampleData::orders() as $o) {
            if (in_array($o['status'], ['awaiting_payment', 'paid'], true)) {
                foreach ($o['items'] as $line) {
                    $this->assertFalse(Catalog::isSoldOut(Catalog::item($line['menu_item'])), "{$o['order_no']} has sold-out {$line['name']}");
                }
            }
        }
    }

    public function test_the_database_holds_the_whole_sample_café(): void
    {
        $this->assertCount(6, Catalog::categories());
        $this->assertCount(27, Catalog::items());
        $this->assertCount(41, Catalog::ingredients());
        $this->assertCount(5, Catalog::users());
        $this->assertCount(5, Catalog::tables());
        $this->assertCount(13, Orders::today());

        // Each menu item reads back exactly as the sample describes it, options and recipe included
        foreach (SampleData::items() as $sample) {
            $item = Catalog::item($sample['code']);
            $this->assertSame($sample['name'], $item['name']);
            $this->assertEquals($sample['price'], $item['price']);
            $this->assertEquals($sample['options'], $item['options'], $sample['code']);
            $this->assertEquals(SampleData::recipes()[$sample['code']], Catalog::recipes()[$sample['code']], $sample['code']);
        }
    }

    public function test_sample_orders_read_back_from_the_database_as_they_were_described(): void
    {
        $stored = Orders::today();

        foreach (SampleData::orders() as $sample) {
            $order = collect($stored)->firstWhere('id', $sample['id']);

            $this->assertSame($sample['order_no'], $order['order_no']);
            $this->assertSame($sample['status'], $order['status']);
            $this->assertEquals($sample['total'], $order['total'], $sample['order_no']);
            $this->assertEquals($sample['items'], $order['items'], $sample['order_no']);
            $this->assertEquals($sample['status_log'], $order['status_log'], $sample['order_no']);
            $this->assertEquals($sample['payment'], $order['payment'], $sample['order_no']);
            $this->assertEquals($sample['discount'], $order['discount'], $sample['order_no']);
        }
    }

    public function test_finds_orders_by_id_or_public_token(): void
    {
        $this->assertSame(8, SampleData::order(8)['id']);
        $this->assertSame(8, SampleData::order('demo-0008')['id']);
        $this->assertNull(SampleData::order(99));

        $this->assertSame(8, Orders::find(8)['id']);
        $this->assertSame(8, Orders::findByToken('demo-0008')['id']);
        $this->assertNull(Orders::find(99));
        $this->assertNull(Orders::find('demo-0008'), 'staff screens find orders by number only');
        $this->assertNull(Orders::findByToken('8'), 'customers find orders by token only');
    }
}
