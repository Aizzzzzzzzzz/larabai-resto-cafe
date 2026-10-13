<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\MenuItem;
use App\Models\OrderItem;
use Tests\TestCase;

/** Menu & prices: list, filters, availability, item editor and categories that really change the menu. */
class MenuTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->asAdmin();
    }

    private function item(array $overrides = []): array
    {
        return array_merge([
            '_modal' => 'itemModal', 'mode' => 'add', 'name' => 'Hazelnut Latte', 'category' => 'coffee', 'price' => 175,
            'description' => 'Espresso, milk and hazelnut syrup.', 'large' => 20, 'template' => 'coffee', 'is_best_seller' => 0, 'is_new' => 1,
            'ingredients' => [['code' => 'beans', 'qty' => 18], ['code' => 'milk', 'qty' => 180]],
        ], $overrides);
    }

    // ---- List ----------------------------------------------------------------------------------

    public function test_list_shows_every_item_with_price_options_ingredients_and_availability(): void
    {
        $this->get(route('admin.menu-items.index'))
            ->assertOk()
            ->assertSee('Café Latte')
            ->assertSee('Large ₱165', false)
            ->assertSee('Size (L +₱20) · Add-ons (6)', false)
            ->assertSee('Espresso beans 18 g · Fresh milk 180 ml')
            ->assertSee('Taro Milk Tea')
            ->assertSee('Hidden from ordering')
            ->assertSee('On sale')
            ->assertSee('data-action="'.route('admin.menu-items.update', 'latte').'"', false);
    }

    public function test_tabs_and_search_filter_on_the_server(): void
    {
        $this->get(route('admin.menu-items.index', ['cat' => 'meals']))
            ->assertSee('Beef Tapa')->assertDontSee('Café Latte');

        $this->get(route('admin.menu-items.index', ['q' => 'MATCHA']))
            ->assertSee('Matcha Latte')->assertSee('Matcha Frappe')->assertDontSee('Beef Tapa');

        $this->get(route('admin.menu-items.index', ['cat' => 'coffee', 'q' => 'zzz']))->assertSee('No items match.');
        $this->get(route('admin.menu-items.index', ['cat' => 'bogus']))->assertSee('Beef Tapa'); // unknown tab = all
    }

    public function test_filter_forms_keep_each_others_values(): void
    {
        $this->get(route('admin.menu-items.index', ['cat' => 'coffee', 'q' => 'latte']))
            ->assertSee('<input type="hidden" name="q" value="latte">', false)
            ->assertSee('<input type="hidden" name="cat" value="coffee">', false);
    }

    // ---- Availability --------------------------------------------------------------------------

    public function test_availability_switch_returns_to_the_same_filtered_list(): void
    {
        $this->patch(route('admin.menu-items.availability', ['latte', 'cat' => 'coffee']), ['availability' => 'off'])
            ->assertRedirect(route('admin.menu-items.index', ['cat' => 'coffee']))
            ->assertSessionHas('status', 'Café Latte: sold out.');
        $this->assertTrue(MenuItem::where('code', 'latte')->value('sold_out_override'));
        $this->assertSame('Marked sold out', AuditLog::latest('id')->first()->action);

        $this->patch(route('admin.menu-items.availability', 'latte'), ['availability' => 'on']);
        $this->assertFalse(MenuItem::where('code', 'latte')->value('sold_out_override'), 'always available');

        $this->patch(route('admin.menu-items.availability', 'latte'), ['availability' => 'auto']);
        $this->assertNull(MenuItem::where('code', 'latte')->value('sold_out_override'), 'follows stock');

        $this->patch(route('admin.menu-items.availability', 'latte'), ['availability' => 'maybe'])->assertSessionHasErrors('availability');
        $this->patch(route('admin.menu-items.availability', 'pizza'), ['availability' => 'on'])->assertNotFound();
    }

    // ---- Item editor ---------------------------------------------------------------------------

    public function test_add_an_item(): void
    {
        $this->post(route('admin.menu-items.store'), $this->item())
            ->assertRedirect(route('admin.menu-items.index', ['cat' => 'coffee']))
            ->assertSessionHas('status', 'Added Hazelnut Latte to the menu.');

        $item = MenuItem::where('name', 'Hazelnut Latte')->firstOrFail();
        $this->assertSame('hazelnut-latte', $item->code);
        $this->assertSame([175.0, true, false, true], [$item->price, $item->is_new, $item->is_best_seller, $item->is_active]);
        $this->assertSame('coffee', $item->category->code);

        // The coffee template: temperature, size (Large adds ₱20) and the coffee add-ons — with their stock rules
        $this->assertSame(['temp', 'size', 'addons'], $item->optionGroups->pluck('code')->all());
        $this->assertSame(20.0, $item->optionGroups[1]->choices->firstWhere('code', 'l')->extra_price);
        $oat = $item->optionGroups[2]->choices->firstWhere('code', 'oat');
        $this->assertSame('replace', $oat->ingredients->first()->pivot->action);

        // The recipe is saved in the order it was typed
        $this->assertSame([['beans', 18.0], ['milk', 180.0]], $item->ingredients->map(fn ($i) => [$i->code, (float) $i->pivot->qty_per_serving])->all());

        $this->assertSame('Added menu item', AuditLog::latest('id')->first()->action);
        $this->assertSame('Hazelnut Latte · ₱175.00', AuditLog::latest('id')->first()->detail);

        // It is on the customer menu, and ordering it uses its own recipe
        $this->withSession(['qr' => ['type' => 'dine-in', 'table' => 1]])->get(route('customer.menu'))->assertSee('Hazelnut Latte');
        $this->post(route('customer.cart.store'), ['menu_item' => 'hazelnut-latte', 'qty' => 1, 'options' => ['temp' => 'hot', 'size' => 'l']])->assertSessionHasNoErrors();
        $this->assertSame(195, session('cart')[0]['unit_price']);
    }

    public function test_two_items_with_similar_names_get_distinct_codes(): void
    {
        $this->post(route('admin.menu-items.store'), $this->item(['name' => 'Hazelnut Latte!']));
        $this->post(route('admin.menu-items.store'), $this->item(['name' => 'Hazelnut Latte?']));

        $this->assertSame(['hazelnut-latte', 'hazelnut-latte-2'], MenuItem::where('name', 'like', 'Hazelnut%')->orderBy('id')->pluck('code')->all());
    }

    public function test_edit_an_item(): void
    {
        $edit = $this->item(['mode' => 'edit', 'name' => 'Café Latte', 'price' => 150, 'large' => 25, 'ingredients' => [['code' => 'beans', 'qty' => 20], ['code' => 'milk', 'qty' => 200], ['code' => 'vanilla', 'qty' => 10]]]);
        unset($edit['template']);

        $this->put(route('admin.menu-items.update', 'latte'), $edit)->assertRedirect()->assertSessionHas('status', 'Saved Café Latte.');

        $item = MenuItem::where('code', 'latte')->first();
        $this->assertSame(150.0, $item->price);
        $this->assertSame(25.0, $item->optionGroups->firstWhere('code', 'size')->choices->firstWhere('code', 'l')->extra_price);
        $this->assertSame([['beans', 20.0], ['milk', 200.0], ['vanilla', 10.0]], $item->ingredients->map(fn ($i) => [$i->code, (float) $i->pivot->qty_per_serving])->all());
        $this->assertSame('Café Latte · price ₱145.00 → ₱150.00', AuditLog::latest('id')->first()->detail);

        // A price change reaches the customer's cart; a past order keeps the price it was sold at
        $this->withSession(['qr' => ['type' => 'dine-in', 'table' => 1]])->post(route('customer.cart.store'), ['menu_item' => 'latte', 'qty' => 1, 'options' => ['temp' => 'hot', 'size' => 'l']]);
        $this->assertSame(175, session('cart')[0]['unit_price']);
        $this->assertSame(145.0, OrderItem::where('item_name', 'Café Latte')->where('order_id', 1)->value('unit_price'));
    }

    public function test_an_item_that_was_never_sold_is_deleted_and_one_with_sales_is_only_switched_off(): void
    {
        $this->post(route('admin.menu-items.store'), $this->item());

        $this->delete(route('admin.menu-items.destroy', 'hazelnut-latte'))->assertRedirect(route('admin.menu-items.index'))->assertSessionHas('status', 'Removed Hazelnut Latte from the menu.');
        $this->assertNull(MenuItem::where('code', 'hazelnut-latte')->first());

        $this->delete(route('admin.menu-items.destroy', 'latte'))->assertRedirect(route('admin.menu-items.index'));
        $this->assertFalse(MenuItem::where('code', 'latte')->value('is_active'), 'kept, so past orders keep their lines');
        $this->assertSame('Removed menu item', AuditLog::latest('id')->first()->action);

        $this->get(route('admin.menu-items.index'))->assertDontSee('data-action="'.route('admin.menu-items.update', 'latte').'"', false);
        $this->withSession(['qr' => ['type' => 'dine-in', 'table' => 1]])->get(route('customer.menu'))->assertDontSee('Café Latte');
        $this->asCashier()->get(route('cashier.receipt', 1))->assertSee('Café Latte'); // an old receipt still reads the same

        $this->asAdmin()->delete(route('admin.menu-items.destroy', 'latte'))->assertNotFound(); // already off the menu
        $this->delete(route('admin.menu-items.destroy', 'pizza'))->assertNotFound();
        $this->put(route('admin.menu-items.update', 'pizza'), ['name' => ''] + $this->item())->assertNotFound();
    }

    public function test_item_validation(): void
    {
        $url = route('admin.menu-items.store');

        $this->post($url, $this->item(['name' => '']))->assertSessionHasErrors(['name' => 'Enter the item name.']);
        $this->post($url, $this->item(['name' => 'café latte']))->assertSessionHasErrors('name'); // taken
        $this->post($url, $this->item(['price' => 0]))->assertSessionHasErrors(['price' => 'Enter a price greater than zero.']);
        $this->post($url, $this->item(['category' => 'sushi']))->assertSessionHasErrors('category');
        $this->post($url, $this->item(['template' => 'bogus']))->assertSessionHasErrors('template');
        $this->post($url, $this->item(['ingredients' => []]))->assertSessionHasErrors(['ingredients' => 'Add at least one ingredient so selling it deducts from Stock.']);
        $this->post($url, $this->item(['ingredients' => [['code' => 'beans', 'qty' => 0]]]))->assertSessionHasErrors('ingredients.0.qty');
        $this->post($url, $this->item(['ingredients' => [['code' => 'unicorn', 'qty' => 5]]]))->assertSessionHasErrors('ingredients.0.code');
        $this->post($url, $this->item(['ingredients' => [['code' => 'beans', 'qty' => 5], ['code' => 'beans', 'qty' => 6]]]))
            ->assertSessionHasErrors(['ingredients' => 'An ingredient is listed twice. Combine them into one row.']);
    }

    public function test_large_price_is_only_asked_for_items_with_a_size(): void
    {
        $url = route('admin.menu-items.store');

        // The coffee template has sizes, so "Large adds" is required
        $this->post($url, $this->item(['large' => null]))->assertSessionHasErrors(['large' => 'Enter how much Large adds (0 if the same price).']);
        $this->post($url, $this->item(['large' => 0]))->assertSessionHasNoErrors();

        // Rice meals have no size
        $this->post($url, $this->item(['name' => 'Pork Sisig', 'template' => 'meal', 'large' => null, 'category' => 'meals']))->assertSessionHasNoErrors();
        $this->assertNull(MenuItem::where('name', 'Pork Sisig')->first()->optionGroups->firstWhere('code', 'size'));
    }

    public function test_an_existing_item_keeps_its_options_template(): void
    {
        $edit = $this->item(['mode' => 'edit', 'name' => 'Café Latte']);

        $this->put(route('admin.menu-items.update', 'latte'), $edit)->assertSessionHasErrors('template'); // template is for new items only
        $this->put(route('admin.menu-items.update', 'tapa'), ['name' => 'Beef Tapa', 'template' => null, 'large' => null, 'category' => 'meals'] + $edit)->assertSessionHasNoErrors(); // no size, so no Large price needed
    }

    public function test_a_failed_save_reopens_the_editor_with_the_typed_rows(): void
    {
        $this->post(route('admin.menu-items.store'), $this->item(['name' => '', 'ingredients' => [['code' => 'milk', 'qty' => 99]]]));

        $html = $this->get(route('admin.menu-items.index'))->assertSee('Enter the item name.')->assertSee('value="99"', false)->getContent();

        $this->assertMatchesRegularExpression('/id="itemModal"[^>]*data-modal-autoopen/', $html);
        $this->assertMatchesRegularExpression('/name="ingredients\[0\]\[code\]"/', $html);
    }

    public function test_the_edit_modal_keeps_put_after_a_failed_save(): void
    {
        $this->put(route('admin.menu-items.update', 'latte'), ['_modal' => 'itemModal', 'mode' => 'edit', '_method' => 'PUT', 'name' => ''])
            ->assertSessionHasErrors('name');

        $this->get(route('admin.menu-items.index'))
            ->assertSee('<input type="hidden" name="_method" value="PUT">', false)
            ->assertSee('Edit menu item');
    }

    // ---- Categories ----------------------------------------------------------------------------

    public function test_add_and_rename_a_category(): void
    {
        $this->post(route('admin.categories.store'), ['_modal' => 'categoriesModal', 'name' => 'Desserts'])
            ->assertRedirect(route('admin.menu-items.index'))->assertSessionHas('status', 'Added Desserts.');

        $dessert = Category::where('name', 'Desserts')->firstOrFail();
        $this->assertSame(['desserts', 7, false], [$dessert->code, $dessert->sort_order, $dessert->is_drink]);
        $this->get(route('admin.menu-items.index'))->assertSee('Desserts');

        $this->put(route('admin.categories.update', 'coffee'), ['name' => 'Hot & iced coffee'])->assertRedirect();
        $this->assertSame('Hot & iced coffee', Category::where('code', 'coffee')->value('name'));
        $this->assertSame('Coffee → Hot & iced coffee', AuditLog::latest('id')->first()->detail);

        $this->put(route('admin.categories.update', 'coffee'), ['name' => 'Coffee'])->assertRedirect(); // its own name
        $this->assertSame('Coffee', Category::where('code', 'coffee')->value('name'));

        // An empty category can be removed
        $this->delete(route('admin.categories.destroy', 'desserts'))->assertRedirect(route('admin.menu-items.index'));
        $this->assertNull(Category::where('code', 'desserts')->first());
    }

    public function test_category_validation(): void
    {
        $this->post(route('admin.categories.store'), ['name' => ''])->assertSessionHasErrors(['name' => 'Enter a category name.']);
        $this->post(route('admin.categories.store'), ['name' => 'milk TEA'])->assertSessionHasErrors(['name' => 'milk TEA already exists.']);
        $this->put(route('admin.categories.update', 'coffee'), ['name' => 'Frappe'])->assertSessionHasErrors('name');
        $this->put(route('admin.categories.update', 'sushi'), ['name' => ''])->assertNotFound();
    }

    public function test_a_category_with_items_cannot_be_deleted(): void
    {
        $this->delete(route('admin.categories.destroy', 'coffee'))
            ->assertRedirect(route('admin.menu-items.index'))
            ->assertSessionHasErrors(['category' => 'Coffee still has items. Move or remove them first.']);

        $this->delete(route('admin.categories.destroy', 'sushi'))->assertNotFound();
    }

    public function test_the_categories_modal_lists_counts_and_has_its_own_forms(): void
    {
        $this->get(route('admin.menu-items.index'))
            ->assertSee('id="categoriesModal"', false)
            ->assertSee('6 items')
            ->assertSee('action="'.route('admin.categories.update', 'coffee').'"', false)
            ->assertSee('data-confirm-twice="Tap again to delete"', false);
    }
}
