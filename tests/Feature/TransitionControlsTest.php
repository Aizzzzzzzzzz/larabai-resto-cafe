<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Services\Catalog;
use App\Services\Orders;
use Tests\TestCase;

/**
 * Playbook rule: "every transition needs a visible control — a workflow arrow with no button is a bug
 * factory". One test per row of the order state table in development-plan.md §3, checking the control is on
 * the screen of whoever does the move, pointing at the right route. (The server side of each move is covered
 * in the module tests and WorkflowTest.)
 */
class TransitionControlsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->asAdmin(); // the owner can open every module's screens
    }

    private function page(string $url, array $session = []): string
    {
        return $this->withSession($session + ['qr' => ['type' => 'dine-in', 'table' => 3], 'cart' => $this->cart(), 'my_orders' => ['demo-0012', 'demo-0008']])
            ->get($url)->assertOk()->getContent();
    }

    private function cart(): array
    {
        return [['id' => 1, 'menu_item' => 'latte', 'choices' => ['temp' => ['hot'], 'size' => ['m']], 'notes' => null, 'qty' => 1, 'name' => 'Café Latte', 'unit_price' => 145]];
    }

    private function assertHasForm(string $html, string $action, ?string $method = null, ?string $button = null): void
    {
        $this->assertMatchesRegularExpression('#<form[^>]*action="'.preg_quote($action, '#').'"#', $html, "no form posting to {$action}");
        if ($method) {
            $this->assertMatchesRegularExpression('#action="'.preg_quote($action, '#').'".*?name="_method" value="'.$method.'"#s', $html);
        }
        if ($button) {
            $this->assertStringContainsString($button, $html, "no \"{$button}\" control");
        }
    }

    public function test_1_an_order_is_placed_by_the_customer_and_by_the_cashier(): void
    {
        $this->assertHasForm($this->page(route('customer.checkout.create')), route('customer.checkout.store'), null, 'Place order');
        $this->assertHasForm($this->page(route('cashier.pos.index')), route('cashier.pos.store'), null, 'Charge');
    }

    public function test_2_the_customer_pays_in_the_simulated_wallet(): void
    {
        // The wallet screen belongs to an order that is still waiting for its wallet payment, so place one first
        $this->withSession(['qr' => ['type' => 'dine-in', 'table' => 3], 'cart' => $this->cart()])
            ->post(route('customer.checkout.store'), ['order_type' => 'dine-in', 'payment_method' => 'GCASH']);
        $token = \App\Models\Order::latest('id')->value('public_token');

        $this->assertHasForm($this->page(route('customer.pay.create', $token), ['my_orders' => [$token]]), route('customer.pay.store', $token), null, 'Pay ₱145.00');
    }

    public function test_3_the_cashier_takes_payment_from_the_orders_list(): void
    {
        $orders = $this->page(route('cashier.orders.index'));
        $this->assertStringContainsString('href="'.route('cashier.payments.create', 12).'"', $orders);
        $this->assertHasForm($this->page(route('cashier.payments.create', 12)), route('cashier.payments.store', 12), null, 'Confirm');
    }

    public function test_3b_cash_on_delivery_is_collected_from_the_orders_list(): void
    {
        $orders = $this->page(route('cashier.orders.index', ['tab' => 'awaiting']));

        $this->assertStringContainsString('Collect COD payment', $orders);
        $this->assertStringContainsString('href="'.route('cashier.payments.create', 9).'"', $orders); // 0009: cooking, unpaid
    }

    public function test_4_an_unpaid_order_is_cancelled_by_the_customer_or_the_cashier(): void
    {
        $this->assertHasForm($this->page(route('customer.orders.show', 'demo-0012')), route('customer.orders.cancel', 'demo-0012'), null, 'Cancel order');

        $orders = $this->page(route('cashier.orders.index'));
        $this->assertStringContainsString('data-action="'.route('cashier.orders.cancel', 12).'"', $orders);
        $this->assertHasForm($orders, route('cashier.orders.index'), null, 'Keep order'); // the reason modal they open
    }

    public function test_5_to_9_the_kitchen_moves_a_ticket_with_visible_buttons(): void
    {
        $board = $this->page(route('kitchen.display'));
        $move = fn (string $move) => '#<button[^>]*name="move" value="'.$move.'"#';

        $this->assertMatchesRegularExpression($move('start'), $board);   // 5  New → Preparing
        $this->assertMatchesRegularExpression($move('back'), $board);    // 6  Preparing → back to New
        $this->assertMatchesRegularExpression($move('ready'), $board);   // 7  Preparing → Ready
        $this->assertMatchesRegularExpression($move('done'), $board);    // 8  Ready → Completed (kitchen side)
        $this->assertMatchesRegularExpression($move('recall'), $board);  // 9 / 9b  Ready → Preparing, Completed → Ready

        $this->assertHasForm($board, route('kitchen.orders.status', 10), 'PATCH'); // a ticket's form
        $this->assertStringContainsString('Recently done:', $board);
        $this->assertStringContainsString('title="Recall to Ready"', $board);     // 9  from the strip
    }

    public function test_8_the_cashier_marks_a_ready_order_served_picked_up_or_delivered(): void
    {
        $orders = $this->page(route('cashier.orders.index', ['tab' => 'active']));

        $this->assertHasForm($orders, route('cashier.orders.complete', 6), 'PATCH', 'Mark served');       // dine-in
        $this->assertHasForm($orders, route('cashier.orders.complete', 7), 'PATCH', 'Mark picked up');    // take-out
    }

    public function test_10_a_paid_order_is_voided_by_the_cashier(): void
    {
        $orders = $this->page(route('cashier.orders.index', ['tab' => 'active']));

        $this->assertStringContainsString('data-action="'.route('cashier.orders.void', 8).'"', $orders);   // preparing
        $this->assertStringContainsString('data-action="'.route('cashier.orders.void', 10).'"', $orders);  // just paid
        $this->assertStringContainsString('data-submit="Void order"', $orders);
    }

    public function test_the_other_switches_have_controls_too(): void
    {
        // Sold-out: the kitchen's switches and the owner's availability select
        $this->assertHasForm($this->page(route('kitchen.display')), route('kitchen.availability.update', 'latte'), 'PATCH');
        $this->assertHasForm($this->page(route('admin.menu-items.index')), route('admin.menu-items.availability', 'latte'), 'PATCH');

        // Stock: restock, count, add and edit ingredient
        $stock = $this->page(route('inventory.ingredients.index'));
        $this->assertStringContainsString('data-action="'.route('inventory.ingredients.restock', 'milk').'"', $stock);
        $this->assertStringContainsString('data-action="'.route('inventory.ingredients.update', 'milk').'"', $stock);
        $this->assertHasForm($stock, route('inventory.ingredients.store'));

        // The customer's cart controls, and logging out from every staff screen
        $cart = $this->page(route('customer.cart.index'));
        $this->assertHasForm($cart, route('customer.cart.update', 1), 'PATCH');
        $this->assertHasForm($cart, route('customer.cart.destroy', 1), 'DELETE');
        foreach ([route('cashier.pos.index'), route('kitchen.display'), route('inventory.ingredients.index'), route('admin.dashboard')] as $screen) {
            $this->assertHasForm($this->page($screen), route('logout'));
        }
    }

    public function test_no_open_order_is_a_dead_end(): void
    {
        // Every status that isn't final shows a way forward somewhere
        $forward = [
            OrderStatus::AwaitingPayment->value => ['cashier.payments.create', 12],
            OrderStatus::Paid->value => ['kitchen.orders.status', 10],
            OrderStatus::Preparing->value => ['kitchen.orders.status', 8],
            OrderStatus::Ready->value => ['cashier.orders.complete', 6],
        ];

        foreach (Orders::today() as $order) {
            if (! isset($forward[$order['status']])) {
                $this->assertContains($order['status'], ['completed', 'cancelled', 'voided'], "{$order['order_no']} is {$order['status']} and has no way forward");
            }
        }
        foreach ($forward as $status => [$route, $id]) {
            $this->assertNotEmpty(route($route, $id), "{$status} has a route forward");
        }
        $this->assertCount(4, $forward); // OrderStatus has 7 cases: these 4 are open, the other 3 are final
        $this->assertCount(7, OrderStatus::cases());
    }
}
