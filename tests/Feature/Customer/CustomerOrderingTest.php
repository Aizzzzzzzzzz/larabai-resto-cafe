<?php

namespace Tests\Feature\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * UI scaffold Step 2 — customer module: QR session, menu, session cart (real), checkout,
 * simulated wallet, receipt and tracker (sample orders, nothing saved).
 */
class CustomerOrderingTest extends TestCase
{
    private function atTable(int $table = 3): static
    {
        return $this->withSession(['qr' => ['type' => 'dine-in', 'table' => $table]]);
    }

    private function atCounter(): static
    {
        return $this->withSession(['qr' => ['type' => 'counter', 'table' => null]]);
    }

    private function addLatte(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->post(route('customer.cart.store'), array_merge([
            'menu_item' => 'latte',
            'qty' => 2,
            'options' => ['temp' => 'iced', 'size' => 'l', 'addons' => ['oat']],
            'notes' => 'Less ice',
        ], $overrides));
    }

    // ---- QR session ----------------------------------------------------------------------------

    public function test_menu_without_a_scanned_qr_asks_to_scan(): void
    {
        $this->get(route('customer.menu'))->assertOk()->assertSee('Scan the QR code on your table');
    }

    public function test_screens_after_the_menu_need_a_scanned_qr(): void
    {
        foreach ([route('customer.cart.index'), route('customer.checkout.create'), route('customer.orders.index'), route('customer.orders.show', 'demo-0008')] as $url) {
            $this->get($url)->assertRedirect(route('customer.menu'));
        }
    }

    public function test_scanning_another_table_starts_a_fresh_cart(): void
    {
        $this->atTable(3)->addLatte();
        $this->get(URL::signedRoute('customer.session.table', ['table' => 3]))->assertSessionHas('cart');
        $this->get(URL::signedRoute('customer.session.table', ['table' => 4]))->assertSessionMissing('cart');
    }

    public function test_unknown_table_is_not_found(): void
    {
        $this->get(URL::signedRoute('customer.session.table', ['table' => 9]))->assertNotFound();
    }

    // ---- Menu ----------------------------------------------------------------------------------

    public function test_menu_lists_every_category_and_item_with_sold_out_disabled(): void
    {
        $this->atTable(3)->get(route('customer.menu'))
            ->assertOk()
            ->assertSee('TABLE 3 · DINE-IN')
            ->assertSeeInOrder(['Coffee', 'Non-coffee', 'Milk tea', 'Frappe', 'Rice meals', 'Pastries &amp; snacks'], false)
            ->assertSee('Café Latte')
            ->assertSee('from ₱145', false)
            ->assertSee('data-item="taro" data-search="taro milk tea creamy taro root with jasmine tea." disabled', false)
            ->assertSee('<template id="item-latte"', false)
            ->assertDontSee('<template id="item-taro"', false)
            ->assertDontSee('cart-bar', false);
    }

    public function test_counter_qr_shows_counter_label(): void
    {
        $this->atCounter()->get(route('customer.menu'))->assertSee('COUNTER ORDER');
    }

    // ---- Cart (real, in the session) -----------------------------------------------------------

    public function test_adding_an_item_prices_it_on_the_server(): void
    {
        $this->atTable()->addLatte(['price' => 1])
            ->assertRedirect(route('customer.menu'))
            ->assertSessionHas('status', 'Added 2 × Café Latte');

        // ₱145 + ₱20 large + ₱40 oat milk = ₱205 each; a posted "price" is ignored
        $this->get(route('customer.cart.index'))
            ->assertSee('Iced · Large (16 oz) · Oat milk')
            ->assertSee('Note: Less ice')
            ->assertSee('₱205.00 each')
            ->assertSee('Continue to checkout · ₱410.00');

        $this->get(route('customer.menu'))->assertSee('<span class="cart-count">2</span>', false)->assertSee('₱410');
    }

    public function test_the_same_item_and_options_merge_into_one_line(): void
    {
        $this->atTable()->addLatte();
        $this->addLatte(['qty' => 1]);

        $this->assertCount(1, session('cart'));
        $this->assertSame(3, session('cart')[0]['qty']);
    }

    public function test_options_are_checked_against_the_item(): void
    {
        $this->atTable()->addLatte(['options' => ['temp' => 'warm', 'size' => 'l']])->assertSessionHasErrors('options');
        $this->addLatte(['options' => ['size' => 'l']])->assertSessionHasErrors('options'); // temperature is required
        $this->addLatte(['options' => ['temp' => ['hot', 'iced'], 'size' => 'l']])->assertSessionHasErrors('options');
        $this->addLatte(['qty' => 21])->assertSessionHasErrors('qty');
        $this->addLatte(['menu_item' => 'pizza'])->assertSessionHasErrors('menu_item');
        $this->assertNull(session('cart'));
    }

    public function test_sold_out_items_cannot_be_added(): void
    {
        $this->atTable()->post(route('customer.cart.store'), ['menu_item' => 'taro', 'qty' => 1, 'options' => ['size' => 'm', 'sugar' => 's100']])
            ->assertSessionHasErrors(['menu_item' => 'Sorry, Taro Milk Tea just sold out.']);
    }

    public function test_cart_quantity_can_change_and_lines_can_be_removed(): void
    {
        $this->atTable()->addLatte();
        $id = session('cart')[0]['id'];

        $this->patch(route('customer.cart.update', $id), ['qty' => 5])->assertRedirect(route('customer.cart.index'));
        $this->assertSame(5, session('cart')[0]['qty']);

        $this->patch(route('customer.cart.update', $id), ['qty' => 0])->assertSessionHas('status', 'Removed Café Latte');
        $this->assertSame([], session('cart'));

        $this->addLatte();
        $this->delete(route('customer.cart.destroy', session('cart')[0]['id']))->assertSessionHas('status', 'Removed Café Latte');
        $this->get(route('customer.cart.index'))->assertSee('Your order is empty');
    }

    public function test_cart_notices_a_changed_price(): void
    {
        $this->atTable()->withSession(['cart' => [[
            'id' => 1, 'menu_item' => 'americano', 'choices' => ['temp' => ['hot'], 'size' => ['m']],
            'notes' => null, 'qty' => 1, 'name' => 'Americano', 'unit_price' => 99,
        ]]])->get(route('customer.cart.index'))
            ->assertSee('Americano is now ₱110.00 each.')
            ->assertSee('₱110.00 each');
    }

    // ---- Checkout ------------------------------------------------------------------------------

    public function test_checkout_with_an_empty_cart_goes_back_to_the_cart(): void
    {
        $this->atTable()->get(route('customer.checkout.create'))->assertRedirect(route('customer.cart.index'));
    }

    public function test_table_qr_offers_dine_in_or_take_out(): void
    {
        $this->atTable(3)->addLatte();

        $this->get(route('customer.checkout.create'))
            ->assertOk()
            ->assertSee('Dine-in · Table 3')
            ->assertSee('Take-out')
            ->assertDontSee('Delivery address');

        $this->post(route('customer.checkout.store'), ['order_type' => 'delivery', 'payment_method' => 'CASH'])
            ->assertSessionHasErrors('order_type');
    }

    public function test_delivery_needs_an_address_and_nothing_else_personal(): void
    {
        $this->atCounter()->addLatte();

        $this->get(route('customer.checkout.create'))->assertSee('Delivery address')->assertSee('Cash on delivery')->assertDontSee('Mobile');

        $this->post(route('customer.checkout.store'), ['order_type' => 'delivery', 'payment_method' => 'CASH'])
            ->assertSessionHasErrors(['delivery_address' => 'Enter your delivery address so the rider can find you.']);
    }

    public function test_cash_order_is_placed_and_the_cart_clears(): void
    {
        $this->atTable()->addLatte();

        $this->post(route('customer.checkout.store'), ['order_type' => 'dine-in', 'payment_method' => 'CASH'])
            ->assertRedirect(route('customer.orders.success', 'demo-0012'))
            ->assertSessionHas('status', Controller::DEMO_MESSAGE);

        $this->assertNull(session('cart'));
        $this->get(route('customer.orders.success', 'demo-0012'))
            ->assertSee('Order placed. Please pay ₱685.00')
            ->assertSee('Order slip · not yet paid');
    }

    public function test_e_wallet_order_goes_through_the_simulated_wallet(): void
    {
        $this->atTable()->addLatte();

        $this->post(route('customer.checkout.store'), ['order_type' => 'dine-in', 'payment_method' => 'GCASH'])
            ->assertRedirect(route('customer.pay.create', 'demo-0008'));
        $this->assertNotNull(session('cart'), 'the cart stays until the wallet confirms');

        $this->get(route('customer.pay.create', 'demo-0008'))
            ->assertSee('SIMULATED')
            ->assertSee('Pay ₱599.00')
            ->assertSee('wallet-top gcash', false);

        $this->post(route('customer.pay.store', 'demo-0008'))
            ->assertRedirect(route('customer.orders.success', 'demo-0008'));
        $this->assertNull(session('cart'));

        $this->get(route('customer.orders.success', 'demo-0008'))
            ->assertSee('Payment successful')
            ->assertSee('GC-')
            ->assertSee('PAID');
    }

    // ---- Tracker -------------------------------------------------------------------------------

    public function test_my_orders_is_empty_until_an_order_is_placed(): void
    {
        $this->atTable()->get(route('customer.orders.index'))->assertOk()->assertSee('No orders yet');

        $this->withSession(['my_orders' => ['demo-0012', 'demo-0008']])
            ->get(route('customer.orders.index'))
            ->assertRedirect(route('customer.orders.show', 'demo-0008'));
    }

    public function test_tracker_shows_progress_and_tabs_for_each_order(): void
    {
        $this->atTable()->withSession(['my_orders' => ['demo-0012', 'demo-0008']])
            ->get(route('customer.orders.show', 'demo-0008'))
            ->assertOk()
            ->assertSee('Preparing your order')
            ->assertSee('class="order-tabs"', false)
            ->assertSee('GCash · Paid')
            ->assertSee('data-poll=', false)
            ->assertDontSee('Cancel order');
    }

    public function test_customer_can_cancel_only_before_paying(): void
    {
        $this->atTable()->get(route('customer.orders.show', 'demo-0012'))->assertSee('Cancel order');
        $this->post(route('customer.orders.cancel', 'demo-0012'))->assertRedirect(route('customer.orders.show', 'demo-0012'));
        $this->post(route('customer.orders.cancel', 'demo-0008'))->assertForbidden();
    }

    public function test_status_poll_and_unknown_orders(): void
    {
        $this->atTable()->getJson(route('customer.orders.status', 'demo-0009'))->assertExactJson(['status' => 'preparing']);
        $this->get(route('customer.orders.show', 'nope'))->assertNotFound();
    }
}
