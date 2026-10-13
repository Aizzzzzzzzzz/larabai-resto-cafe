<?php

namespace Tests\Feature\Inventory;

use App\Models\AuditLog;
use App\Models\Ingredient;
use App\Models\StockMovement;
use Tests\TestCase;

/** Stock screen: list, alerts, filters, restock and counts that really change stock, add/edit, history. */
class InventoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->asStockClerk();
    }

    private function stock(string $code): float
    {
        return Ingredient::where('code', $code)->value('stock_qty');
    }

    public function test_summary_cards_and_blocked_menu_banner(): void
    {
        $this->get(route('inventory.ingredients.index'))
            ->assertOk()
            ->assertSee('class="kpi warn"', false)
            ->assertSee('class="kpi alert"', false)
            ->assertSeeInOrder(['Ingredients tracked', '41', 'Low stock', '4', 'Out of stock', '1', 'Stock value'])
            ->assertSee('1 menu item unavailable to customers')
            ->assertSee('Taro Milk Tea · needs Taro powder');
    }

    public function test_out_of_stock_rows_come_first_then_low_then_in_stock(): void
    {
        $this->get(route('inventory.ingredients.index'))
            // Out of stock (taro), then the low ones in list order, then everything in stock
            ->assertSeeInOrder(array_map(fn ($c) => '/'.$c.'/restock', ['taro', 'cream', 'creamcheese', 'oat', 'cup16', 'beans']), false)
            ->assertSee('used in every drink')
            ->assertSee('used in 2 items') // matcha: Matcha Latte + Matcha Frappe
            ->assertSee('₱1.20/g', false)->assertSee('₱0.095/ml', false)
            ->assertSee('18 L'); // fresh milk
    }

    public function test_tabs_category_and_search_filter_on_the_server(): void
    {
        $this->get(route('inventory.ingredients.index', ['tab' => 'low']))
            ->assertSee('/taro/restock', false)->assertSee('/oat/restock', false)
            ->assertDontSee('/beans/restock', false);

        $this->get(route('inventory.ingredients.index', ['tab' => 'ok']))
            ->assertSee('Espresso beans')
            ->assertDontSee('/taro/restock', false);

        $this->get(route('inventory.ingredients.index', ['cat' => 'Packaging']))
            ->assertSee('Cup 12 oz')->assertSee('Cup lids')->assertDontSee('Fresh milk');

        $this->get(route('inventory.ingredients.index', ['q' => 'MILK']))
            ->assertSee('Fresh milk')->assertSee('Oat milk')->assertDontSee('Espresso beans');

        $this->get(route('inventory.ingredients.index', ['q' => 'zzz']))->assertSee('No ingredients match.');
    }

    public function test_filter_forms_keep_each_others_values(): void
    {
        $this->get(route('inventory.ingredients.index', ['tab' => 'low', 'cat' => 'Dairy', 'q' => 'cr']))
            ->assertSee('<input type="hidden" name="cat" value="Dairy">', false)
            ->assertSee('<input type="hidden" name="q" value="cr">', false)
            ->assertSee('<input type="hidden" name="tab" value="low">', false);
    }

    public function test_each_row_has_restock_history_and_edit(): void
    {
        $this->get(route('inventory.ingredients.index'))
            ->assertSee('data-action="'.route('inventory.ingredients.restock', 'milk').'"', false)
            ->assertSee('href="'.route('inventory.ingredients.history', 'milk').'"', false)
            ->assertSee('data-action="'.route('inventory.ingredients.update', 'milk').'"', false)
            ->assertSee('data-modal-open="ingredientAdd"', false);
    }

    // ---- Restock -------------------------------------------------------------------------------

    public function test_restock_a_delivery(): void
    {
        $this->post(route('inventory.ingredients.restock', 'taro'), ['mode' => 'add', 'qty' => 1000, 'note' => 'Delivery'])
            ->assertRedirect(route('inventory.ingredients.index'))
            ->assertSessionHas('status', 'Added 1 kg of Taro powder. Now 1 kg.');

        $this->assertSame(1000.0, $this->stock('taro'));

        $move = StockMovement::latest('id')->first();
        $this->assertSame(['PURCHASE', 1000.0, 0.0, 1000.0, 600.0, 'Delivery', 5], [$move->type->value, $move->qty, $move->qty_before, $move->qty_after, $move->cost, $move->note, $move->user_id]);
        $this->assertSame('Restocked', AuditLog::latest('id')->first()->action);
        $this->assertSame('Liza Garcia', AuditLog::latest('id')->first()->user_name);

        // Taro Milk Tea is back on the menu
        $this->withSession(['qr' => ['type' => 'dine-in', 'table' => 1]])->get(route('customer.menu'))->assertSee('<template id="item-taro"', false);
    }

    public function test_restock_validation_messages(): void
    {
        $url = route('inventory.ingredients.restock', 'taro');

        $this->post($url, ['mode' => 'add', 'qty' => 0])->assertSessionHasErrors(['qty' => 'Enter how much arrived, more than zero.']);
        $this->post($url, ['mode' => 'add'])->assertSessionHasErrors(['qty' => 'Enter how much arrived, more than zero.']);
        $this->post($url, ['mode' => 'count', 'qty' => -1])->assertSessionHasErrors(['qty' => 'Enter the counted amount (0 if there is none).']);
        $this->post($url, ['mode' => 'count'])->assertSessionHasErrors(['qty' => 'Enter the counted amount (0 if there is none).']);
        $this->post($url, ['mode' => 'swap', 'qty' => 5])->assertSessionHasErrors('mode');
        $this->post(route('inventory.ingredients.restock', 'nope'), ['mode' => 'add', 'qty' => 5])->assertNotFound();
        $this->post(route('inventory.ingredients.restock', 'nope'), [])->assertNotFound(); // missing beats invalid
    }

    public function test_a_count_of_zero_is_allowed_and_a_matching_count_changes_nothing(): void
    {
        $this->post(route('inventory.ingredients.restock', 'milk'), ['mode' => 'count', 'qty' => 0, 'note' => 'Spoiled'])
            ->assertSessionHas('status', 'Fresh milk set to 0 ml.');

        $this->assertSame(0.0, $this->stock('milk'));
        $move = StockMovement::latest('id')->first();
        $this->assertSame(['ADJUST', -18000.0, 18000.0, 0.0], [$move->type->value, $move->qty, $move->qty_before, $move->qty_after]);

        $movements = StockMovement::count();
        $this->post(route('inventory.ingredients.restock', 'almond'), ['mode' => 'count', 'qty' => 2500])
            ->assertSessionHas('status', 'Count matches. Nothing to change.');
        $this->assertSame($movements, StockMovement::count(), 'a matching count leaves no movement');
    }

    public function test_a_count_can_raise_the_stock_too(): void
    {
        $this->post(route('inventory.ingredients.restock', 'eggs'), ['mode' => 'count', 'qty' => 90])->assertNotFound(); // the code is "egg"
        $this->post(route('inventory.ingredients.restock', 'egg'), ['mode' => 'count', 'qty' => 90]);

        $this->assertSame(90.0, $this->stock('egg'));
        $this->assertSame(6.0, StockMovement::latest('id')->first()->qty);
        $this->assertSame('Stock count', AuditLog::latest('id')->first()->action);
    }

    public function test_only_the_stock_clerk_and_the_owner_can_restock(): void
    {
        $this->asCashier()->post(route('inventory.ingredients.restock', 'taro'), ['mode' => 'add', 'qty' => 1000])->assertForbidden();
        $this->asKitchen()->post(route('inventory.ingredients.restock', 'taro'), ['mode' => 'add', 'qty' => 1000])->assertForbidden();
        $this->assertSame(0.0, $this->stock('taro'));

        $this->asAdmin()->post(route('inventory.ingredients.restock', 'taro'), ['mode' => 'add', 'qty' => 1000])->assertRedirect();
        $this->assertSame(1000.0, $this->stock('taro'));
    }

    // ---- Add / edit ----------------------------------------------------------------------------

    private function ingredient(array $overrides = []): array
    {
        return array_merge([
            '_modal' => 'ingredientAdd', 'name' => 'Hazelnut syrup', 'category' => 'Syrups & sauces', 'unit' => 'ml',
            'reorder_level' => 500, 'restock_qty' => 1500, 'unit_cost' => 0.45, 'opening_stock' => 2000,
        ], $overrides);
    }

    public function test_add_an_ingredient(): void
    {
        $this->post(route('inventory.ingredients.store'), $this->ingredient())
            ->assertRedirect(route('inventory.ingredients.index'))
            ->assertSessionHas('status', 'Added Hazelnut syrup.');

        $ingredient = Ingredient::where('name', 'Hazelnut syrup')->firstOrFail();
        $this->assertSame('hazelnut-syrup', $ingredient->code);
        $this->assertSame(['ml', 2000.0, 500.0, 1500.0, 0.45], [$ingredient->unit->value, $ingredient->stock_qty, $ingredient->reorder_level, $ingredient->restock_qty, $ingredient->unit_cost]);

        // Opening stock is a movement, so the history starts with it
        $move = $ingredient->movements()->first();
        $this->assertSame(['ADJUST', 2000.0, 'Opening stock'], [$move->type->value, $move->qty, $move->note]);
        $this->assertSame('Added ingredient', AuditLog::latest('id')->first()->action);

        $this->get(route('inventory.ingredients.index'))->assertSee('Hazelnut syrup');
    }

    public function test_an_ingredient_without_opening_stock_starts_empty(): void
    {
        $this->post(route('inventory.ingredients.store'), $this->ingredient(['opening_stock' => null, 'name' => 'Pumpkin puree']));

        $ingredient = Ingredient::where('name', 'Pumpkin puree')->firstOrFail();
        $this->assertSame(0.0, $ingredient->stock_qty);
        $this->assertSame(0, $ingredient->movements()->count());
    }

    public function test_add_validation(): void
    {
        $url = route('inventory.ingredients.store');

        $this->post($url, [])->assertSessionHasErrors(['name' => 'Enter a name.', 'category', 'unit', 'reorder_level', 'restock_qty', 'unit_cost']);
        $this->post($url, $this->ingredient(['name' => 'fresh MILK']))->assertSessionHasErrors(['name' => 'fresh MILK already exists.']);
        $this->post($url, $this->ingredient(['unit' => 'kg']))->assertSessionHasErrors('unit');
        $this->post($url, $this->ingredient(['restock_qty' => 0]))->assertSessionHasErrors(['restock_qty' => 'Enter a restock quantity greater than zero.']);
        $this->post($url, $this->ingredient(['unit_cost' => -1]))->assertSessionHasErrors('unit_cost');
        $this->post($url, $this->ingredient(['opening_stock' => -5]))->assertSessionHasErrors('opening_stock');
    }

    public function test_edit_keeps_the_unit_and_stock_out_of_reach(): void
    {
        $edit = ['_modal' => 'ingredientEdit', 'name' => 'Fresh milk (whole)', 'category' => 'Dairy', 'reorder_level' => 12000, 'restock_qty' => 20000, 'unit_cost' => 0.1];

        $this->put(route('inventory.ingredients.update', 'milk'), $edit)->assertRedirect()->assertSessionHas('status', 'Saved Fresh milk (whole).');

        $milk = Ingredient::where('code', 'milk')->first();
        $this->assertSame(['Fresh milk (whole)', 12000.0, 20000.0, 0.1, 18000.0, 'ml'], [$milk->name, $milk->reorder_level, $milk->restock_qty, $milk->unit_cost, $milk->stock_qty, $milk->unit->value]);
        $this->assertStringContainsString('name, reorder level, unit cost', AuditLog::latest('id')->first()->detail);

        $this->put(route('inventory.ingredients.update', 'milk'), ['name' => 'Fresh milk'] + $edit)->assertRedirect(); // its own name is fine
        $this->put(route('inventory.ingredients.update', 'milk'), ['name' => 'Oat milk'] + $edit)->assertSessionHasErrors('name');
        $this->put(route('inventory.ingredients.update', 'milk'), $edit + ['unit' => 'g'])->assertSessionHasErrors('unit');
        $this->put(route('inventory.ingredients.update', 'milk'), $edit + ['opening_stock' => 5])->assertSessionHasErrors('opening_stock');
        $this->put(route('inventory.ingredients.update', 'nope'), $edit)->assertNotFound();
        $this->assertSame(18000.0, $this->stock('milk'));
    }

    public function test_a_failed_save_reopens_its_modal_with_the_typed_values(): void
    {
        $this->post(route('inventory.ingredients.store'), $this->ingredient(['name' => '']));

        $html = $this->get(route('inventory.ingredients.index'))
            ->assertSee('Enter a name.')
            ->assertSee('value="Syrups &amp; sauces"', false)
            ->getContent();

        $this->assertMatchesRegularExpression('/id="ingredientAdd"[^>]*data-modal-autoopen/', $html);
        $this->assertDoesNotMatchRegularExpression('/id="restockModal"[^>]*data-modal-autoopen/', $html);
    }

    // ---- History -------------------------------------------------------------------------------

    public function test_history_lists_movements_newest_first_with_balances(): void
    {
        $this->get(route('inventory.ingredients.history', 'milk'))
            ->assertOk()
            ->assertSee('class="m-staff staff v-inventory-ingredients"', false)
            ->assertSee('Fresh milk')
            ->assertSee('On hand <b>18 L</b>', false)
            ->assertSeeInOrder(['Used in order', 'BB-', 'Used in order', 'Restocked', 'Delivered by Central Luzon Dairy', 'Liza Garcia', 'Stock count', 'Opening stock'])
            ->assertSee('class="r num mv-out"', false)
            ->assertSee('class="r num mv-in"', false)
            ->assertSee('Café Latte');
    }

    public function test_history_shows_returns_for_voided_orders(): void
    {
        // The voided order 0004 (Café Mocha) used ingredients and gave them back
        $this->get(route('inventory.ingredients.history', 'choco'))
            ->assertSee('Returned (cancel/void)')
            ->assertSee('Order voided');
    }

    public function test_history_for_an_ingredient_nobody_used_today(): void
    {
        $this->get(route('inventory.ingredients.history', 'almond'))
            ->assertOk()
            ->assertSee('No stock movements yet');
    }

    public function test_history_for_an_unknown_ingredient(): void
    {
        $this->get(route('inventory.ingredients.history', 'nope'))->assertNotFound();
    }
}
