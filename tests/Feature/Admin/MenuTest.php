<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Controller;
use Tests\TestCase;

/** UI scaffold Step 6 — Menu & prices: list, filters, availability, item editor, categories. */
class MenuTest extends TestCase
{
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
            ->assertSessionHas('status', Controller::DEMO_MESSAGE);

        $this->patch(route('admin.menu-items.availability', 'latte'), ['availability' => 'maybe'])->assertSessionHasErrors('availability');
        $this->patch(route('admin.menu-items.availability', 'pizza'), ['availability' => 'on'])->assertNotFound();
    }

    // ---- Item editor ---------------------------------------------------------------------------

    public function test_add_an_item(): void
    {
        $this->post(route('admin.menu-items.store'), $this->item())
            ->assertRedirect(route('admin.menu-items.index', ['cat' => 'coffee']))
            ->assertSessionHas('status', Controller::DEMO_MESSAGE);
    }

    public function test_edit_and_remove_an_item(): void
    {
        $edit = $this->item(['mode' => 'edit', 'name' => 'Café Latte', 'price' => 150]);
        unset($edit['template']);

        $this->put(route('admin.menu-items.update', 'latte'), $edit)->assertRedirect()->assertSessionHas('status', Controller::DEMO_MESSAGE);
        $this->delete(route('admin.menu-items.destroy', 'latte'))->assertRedirect(route('admin.menu-items.index'));
        $this->delete(route('admin.menu-items.destroy', 'pizza'))->assertNotFound();
        $this->put(route('admin.menu-items.update', 'pizza'), ['name' => ''] + $edit)->assertNotFound();
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
        $this->post($url, $this->item(['template' => 'meal', 'large' => null, 'category' => 'meals']))->assertSessionHasNoErrors();
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
            ->assertRedirect(route('admin.menu-items.index'))->assertSessionHas('status', Controller::DEMO_MESSAGE);
        $this->put(route('admin.categories.update', 'coffee'), ['name' => 'Hot & iced coffee'])->assertRedirect();
        $this->put(route('admin.categories.update', 'coffee'), ['name' => 'Coffee'])->assertRedirect(); // its own name
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
