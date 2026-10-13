<?php

namespace Tests\Feature\Customer;

use App\Models\AuditLog;
use App\Models\DiningTable;
use App\Models\Ingredient;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\StockMovement;
use App\Services\Catalog;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Customer module: QR session, menu, session cart, checkout that really creates the order, the simulated wallet that
 * really pays it (payment row, stock out, audit), the tracker and cancelling. Sample orders 8 and 12 stand in for
 * "this phone's orders" where a test only needs to look at a page.
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

    private function addLatte(array $overrides = []): TestResponse
    {
        return $this->post(route('customer.cart.store'), array_merge([
            'menu_item' => 'latte',
            'qty' => 2,
            'options' => ['temp' => 'iced', 'size' => 'l', 'addons' => ['oat']],
            'notes' => 'Less ice',
        ], $overrides));
    }

    private function stock(string $code): float
    {
        return Ingredient::where('code', $code)->value('stock_qty');
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

    public function test_a_switched_off_table_is_not_found(): void
    {
        DiningTable::where('table_no', 2)->update(['is_active' => false]);
        Catalog::flush();

        $this->get(URL::signedRoute('customer.session.table', ['table' => 2]))->assertNotFound();
    }

    public function test_an_unsigned_table_link_is_refused(): void
    {
        $this->get(route('customer.session.table', ['table' => 3]))->assertForbidden();
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

    public function test_the_menu_follows_stock_and_the_menu_editor(): void
    {
        Ingredient::where('code', 'beans')->update(['stock_qty' => 5]); // not enough for one shot
        MenuItem::where('code', 'ensaymada')->update(['is_active' => false]);

        $this->atTable()->get(route('customer.menu'))
            ->assertSee('data-item="latte" data-search="café latte', false)
            ->assertSee('disabled', false)
            ->assertDontSee('Ensaymada');

        $this->addLatte()->assertSessionHasErrors(['menu_item' => 'Sorry, Café Latte just sold out.']);
    }

    public function test_counter_qr_shows_counter_label(): void
    {
        $this->atCounter()->get(route('customer.menu'))->assertSee('COUNTER ORDER');
    }

    // ---- Cart (in the session) -----------------------------------------------------------------

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

    public function test_cart_drops_an_item_that_left_the_menu(): void
    {
        $this->atTable()->addLatte();
        MenuItem::where('code', 'latte')->update(['is_active' => false]);
        Catalog::flush();

        $this->get(route('customer.cart.index'))->assertSee('Café Latte is no longer on the menu, so it was removed.');
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

    public function test_cash_order_is_really_placed_and_the_cart_clears(): void
    {
        $this->atTable(3)->addLatte();
        $stockBefore = $this->stock('beans');

        $response = $this->post(route('customer.checkout.store'), ['order_type' => 'dine-in', 'payment_method' => 'CASH']);

        $order = Order::latest('id')->first();
        $response->assertRedirect(route('customer.orders.success', $order->public_token));

        // Re-priced on the server, status "awaiting payment", numbered for today, nothing taken from stock yet
        $this->assertSame('awaiting_payment', $order->status->value);
        $this->assertSame('BB-'.now()->format('Ymd').'-0014', $order->order_no);
        $this->assertSame(40, strlen($order->public_token));
        $this->assertSame(410.0, $order->total);
        $this->assertSame(3, $order->table->table_no);
        $this->assertSame('qr', $order->source->value);
        $this->assertFalse($order->stock_deducted);
        $this->assertSame($stockBefore, $this->stock('beans'));
        $this->assertNull($order->payment);
        $this->assertSame(['awaiting_payment'], $order->statusLogs->pluck('status')->map->value->all());

        $line = $order->items->first();
        $this->assertSame([2, 'Café Latte', 205.0], [$line->qty, $line->item_name, $line->unit_price]);
        $this->assertSame('Less ice', $line->notes);
        $this->assertEqualsCanonicalizing(['iced', 'l', 'oat'], $line->options->pluck('choice_code')->all());

        $this->assertNull(session('cart'));
        $this->assertContains($order->public_token, session('my_orders'));

        $this->get(route('customer.orders.success', $order->public_token))
            ->assertSee('Order placed. Please pay ₱410.00')
            ->assertSee('Order slip · not yet paid');
    }

    public function test_e_wallet_payment_really_pays_the_order(): void
    {
        $this->atTable()->addLatte();
        $beans = $this->stock('beans');
        $oat = $this->stock('oat');

        $this->post(route('customer.checkout.store'), ['order_type' => 'dine-in', 'payment_method' => 'GCASH']);
        $order = Order::latest('id')->first();
        $token = $order->public_token;

        $this->assertNotNull(session('cart'), 'the cart stays until the wallet confirms');
        $this->get(route('customer.pay.create', $token))
            ->assertSee('SIMULATED')
            ->assertSee('Pay ₱410.00')
            ->assertSee('wallet-top gcash', false);

        $this->post(route('customer.pay.store', $token))->assertRedirect(route('customer.orders.success', $token));
        $this->assertNull(session('cart'));

        $order->refresh();
        $this->assertSame('paid', $order->status->value);
        $this->assertNotNull($order->paid_at);
        $this->assertTrue($order->stock_deducted);

        // One payment row with a reference number, nobody at the till
        $this->assertSame('GC-'.now()->format('Ymd').'-0009', $order->payment->reference_no); // the sample GCash payments used up to 0008
        $this->assertSame(410.0, $order->payment->amount);
        $this->assertSame('PAID', $order->payment->status->value);
        $this->assertNull($order->payment->received_by);

        // 2 iced large oat lattes: 36 g beans, 360 ml oat milk in place of the fresh milk, 2 large cups
        $this->assertSame($beans - 36, $this->stock('beans'));
        $this->assertSame($oat - 360, $this->stock('oat'));
        $cups = StockMovement::where('order_id', $order->id)->where('type', 'SALE')->where('ingredient_id', Ingredient::where('code', 'cup16')->value('id'))->value('qty');
        $this->assertSame(-2.0, $cups);

        $this->assertSame(['awaiting_payment', 'paid'], $order->statusLogs->pluck('status')->map->value->all());
        $this->assertSame('Customer (QR)', AuditLog::where('action', 'Payment recorded')->latest('id')->first()->user_name);

        $this->get(route('customer.orders.success', $token))
            ->assertSee('Payment successful')
            ->assertSee($order->payment->reference_no)
            ->assertSee('PAID');
    }

    public function test_paying_twice_does_not_charge_twice(): void
    {
        $this->atTable()->addLatte();
        $this->post(route('customer.checkout.store'), ['order_type' => 'dine-in', 'payment_method' => 'MAYA']);
        $token = Order::latest('id')->value('public_token');

        $this->post(route('customer.pay.store', $token));
        $beans = $this->stock('beans');
        $this->post(route('customer.pay.store', $token))->assertRedirect(route('customer.orders.success', $token));

        $this->assertSame(1, Order::latest('id')->first()->payment()->count());
        $this->assertSame($beans, $this->stock('beans'));
        $this->get(route('customer.pay.create', $token))->assertRedirect(route('customer.orders.success', $token));
    }

    public function test_payment_is_refused_if_stock_ran_out_meanwhile(): void
    {
        $this->atTable()->addLatte();
        $this->post(route('customer.checkout.store'), ['order_type' => 'dine-in', 'payment_method' => 'GCASH']);
        $token = Order::latest('id')->value('public_token');

        Ingredient::where('code', 'beans')->update(['stock_qty' => 20]); // two lattes need 36 g

        $this->post(route('customer.pay.store', $token))
            ->assertRedirect(route('customer.pay.create', $token))
            ->assertSessionHas('error');

        $order = Order::latest('id')->first();
        $this->assertSame('awaiting_payment', $order->status->value);
        $this->assertNull($order->payment);
        $this->assertSame(20.0, $this->stock('beans'), 'nothing was taken from stock');
        $this->assertNotNull(session('cart'));
    }

    public function test_cash_on_delivery_takes_the_stock_out_at_once_and_goes_to_the_kitchen(): void
    {
        $this->atCounter()->addLatte(['options' => ['temp' => 'hot', 'size' => 'm']]);
        $milk = $this->stock('milk');

        $this->post(route('customer.checkout.store'), [
            'order_type' => 'delivery', 'payment_method' => 'CASH',
            'delivery_address' => '12 Rizal St., Tarlac City', 'delivery_landmark' => 'Blue gate',
        ])->assertRedirect();

        $order = Order::latest('id')->first();
        $this->assertTrue($order->isCod());
        $this->assertSame(50.0, $order->delivery_fee);
        $this->assertSame(2 * 145 + 50.0, $order->total);
        $this->assertSame('12 Rizal St., Tarlac City', $order->delivery_address);
        $this->assertTrue($order->stock_deducted);
        $this->assertSame($milk - 360, $this->stock('milk'));

        $this->asKitchen()->get(route('kitchen.display'))->assertSee('#'.substr($order->order_no, -4));
    }

    public function test_a_customer_cancelling_a_cod_order_returns_its_stock(): void
    {
        $this->atCounter()->addLatte(['options' => ['temp' => 'hot', 'size' => 'm']]);
        $milk = $this->stock('milk');
        $this->post(route('customer.checkout.store'), ['order_type' => 'delivery', 'payment_method' => 'CASH', 'delivery_address' => '12 Rizal St.']);
        $token = Order::latest('id')->value('public_token');
        $this->assertSame($milk - 360, $this->stock('milk'));

        $this->post(route('customer.orders.cancel', $token))->assertRedirect(route('customer.orders.show', $token));

        $order = Order::latest('id')->first();
        $this->assertSame('cancelled', $order->status->value);
        $this->assertSame('Cancelled by customer', $order->cancel_reason);
        $this->assertFalse($order->stock_deducted);
        $this->assertSame($milk, $this->stock('milk'));
        $this->assertSame('Customer (QR)', AuditLog::where('action', 'Cancelled order')->latest('id')->first()->user_name);
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
        $this->atTable()->withSession(['my_orders' => ['demo-0012', 'demo-0008']]);

        $this->get(route('customer.orders.show', 'demo-0012'))->assertSee('Cancel order');
        $this->post(route('customer.orders.cancel', 'demo-0012'))->assertRedirect(route('customer.orders.show', 'demo-0012'));
        $this->assertSame('cancelled', Order::find(12)->status->value);

        $this->post(route('customer.orders.cancel', 'demo-0008'))->assertForbidden();
        $this->assertSame('preparing', Order::find(8)->status->value);
    }

    public function test_an_order_link_only_works_in_the_phone_that_placed_it(): void
    {
        // Real tokens exist, but this phone's session doesn't hold them
        $this->atTable()->get(route('customer.orders.show', 'demo-0008'))->assertNotFound();
        $this->getJson(route('customer.orders.status', 'demo-0008'))->assertNotFound();
        $this->get(route('customer.orders.success', 'demo-0008'))->assertNotFound();
        $this->get(route('customer.pay.create', 'demo-0011'))->assertNotFound();
        $this->post(route('customer.pay.store', 'demo-0011'))->assertNotFound();
        $this->post(route('customer.orders.cancel', 'demo-0012'))->assertNotFound();

        $this->assertSame('awaiting_payment', Order::find(12)->status->value);
    }

    public function test_status_poll_and_unknown_orders(): void
    {
        $this->atTable()->withSession(['my_orders' => ['demo-0009']])->getJson(route('customer.orders.status', 'demo-0009'))->assertExactJson(['status' => 'preparing']);
        $this->get(route('customer.orders.show', 'nope'))->assertNotFound();
    }
}
