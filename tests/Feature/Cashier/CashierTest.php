<?php

namespace Tests\Feature\Cashier;

use App\Models\AuditLog;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Cashier module: POS charge (validated, re-priced, saved), orders list with stage-specific actions that really
 * move the order, payment with Senior/PWD discount, COD collection, void and cancel, receipt.
 */
class CashierTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->asCashier();
    }

    private function stock(string $code): float
    {
        return Ingredient::where('code', $code)->value('stock_qty');
    }

    // ---- POS -----------------------------------------------------------------------------------

    public function test_pos_shows_the_menu_tiles_and_an_empty_ticket(): void
    {
        $this->get(route('cashier.pos.index'))
            ->assertOk()
            ->assertSee('data-item="latte"', false)
            ->assertSee('data-item="taro" data-cat="milktea" data-search="taro milk tea" disabled', false)
            ->assertSee('<template id="pos-item-latte">', false)
            ->assertDontSee('<template id="pos-item-ensaymada">', false) // no options → added straight away
            ->assertSee('Tap an item on the left to start an order.')
            ->assertSee('name="table_no"', false);
    }

    public function test_charge_creates_the_order_and_goes_to_payment(): void
    {
        $beans = $this->stock('beans');

        $response = $this->post(route('cashier.pos.store'), [
            'order_type' => 'dine-in',
            'table_no' => 4,
            'items' => [
                ['menu_item' => 'latte', 'qty' => 2, 'price' => 1, 'options' => ['temp' => ['iced'], 'size' => ['l']]],
                ['menu_item' => 'ensaymada', 'qty' => 1, 'notes' => 'Warm it'],
            ],
        ]);

        $order = Order::latest('id')->first();
        $response->assertRedirect(route('cashier.payments.create', $order->id))->assertSessionHas('pos_reset', true);
        $this->assertSame('pos', $order->source->value);
        $this->assertSame('awaiting_payment', $order->status->value);
        $this->assertSame('dine-in', $order->order_type->value);
        $this->assertSame(4, $order->table->table_no);
        $this->assertSame(2, $order->created_by, 'the signed-in cashier');
        $this->assertSame(2 * 165.0 + 75.0, $order->total, 'priced from the menu, not from the browser');
        $this->assertSame(['Café Latte', 'Ensaymada'], $order->items->pluck('item_name')->all());
        $this->assertSame('Warm it', $order->items[1]->notes);
        $this->assertFalse($order->stock_deducted);
        $this->assertSame($beans, $this->stock('beans'), 'stock comes out when it is paid');
        $this->assertSame('Created counter order', AuditLog::latest('id')->first()->action);
        $this->assertSame('Jessa Manalo', AuditLog::latest('id')->first()->user_name);

        // The next POS visit empties the browser-kept ticket once
        $this->get(route('cashier.pos.index'))->assertSee('data-reset', false);
        $this->get(route('cashier.pos.index'))->assertDontSee('data-reset', false);
    }

    public function test_take_out_has_no_table(): void
    {
        $this->post(route('cashier.pos.store'), ['order_type' => 'take-out', 'table_no' => 3, 'items' => [['menu_item' => 'ensaymada', 'qty' => 1]]]);

        $this->assertNull(Order::latest('id')->first()->dining_table_id);
    }

    public function test_charge_rejects_bad_tickets(): void
    {
        $this->post(route('cashier.pos.store'), ['order_type' => 'take-out'])->assertSessionHasErrors(['items' => 'Tap an item on the left to start an order.']);
        $this->post(route('cashier.pos.store'), ['order_type' => 'dine-in', 'items' => [['menu_item' => 'ensaymada', 'qty' => 1]]])->assertSessionHasErrors('table_no');
        $this->post(route('cashier.pos.store'), ['order_type' => 'take-out', 'items' => [['menu_item' => 'taro', 'qty' => 1, 'options' => ['size' => ['m'], 'sugar' => ['s50']]]]])
            ->assertSessionHasErrors(['items' => 'Taro Milk Tea just sold out. Remove it from the ticket.']);
        $this->post(route('cashier.pos.store'), ['order_type' => 'take-out', 'items' => [['menu_item' => 'latte', 'qty' => 1, 'options' => ['size' => ['l']]]]])
            ->assertSessionHasErrors(['items' => 'Choose a Temperature for Café Latte.']);
        $this->post(route('cashier.pos.store'), ['order_type' => 'take-out', 'items' => [['menu_item' => 'ensaymada', 'qty' => 51]]])->assertSessionHasErrors('items.0.qty');

        $this->assertSame(13, Order::count(), 'nothing was created');
    }

    // ---- Orders list -----------------------------------------------------------------------------

    public function test_awaiting_tab_lists_unpaid_orders_oldest_first_with_their_actions(): void
    {
        $this->get(route('cashier.orders.index'))
            ->assertOk()
            ->assertSeeInOrder(['BB-', '-0009', 'Collect COD payment', '-0011', 'Take payment', '-0012', '-0013'], false)
            ->assertDontSee('-0008</b>', false)
            ->assertSee('data-action="'.route('cashier.orders.cancel', 13).'"', false);
    }

    public function test_tabs_and_search_filter_on_the_server(): void
    {
        $this->get(route('cashier.orders.index', ['tab' => 'active']))
            ->assertSee('Mark served')       // 0006 dine-in, ready
            ->assertSee('Mark picked up')    // 0007 take-out, ready
            ->assertSee('data-action="'.route('cashier.orders.void', 8).'"', false)
            ->assertDontSee('-0013</b>', false);

        $this->get(route('cashier.orders.index', ['tab' => 'all', 'q' => '0004']))
            ->assertSee('-0004</b>', false)
            ->assertSee('Customer changed their mind')
            ->assertDontSee('-0003</b>', false);

        $this->get(route('cashier.orders.index', ['tab' => 'done', 'q' => 'nothing']))->assertSee('No orders here yet.');
    }

    public function test_kpis_count_today(): void
    {
        // Awaiting: 9, 11, 12, 13 · paid sales exclude the voided order 4
        $this->get(route('cashier.orders.index'))
            ->assertSeeInOrder(['Awaiting payment', '4', 'Orders today', '13'])
            ->assertSee('class="kpi alert"', false);
    }

    public function test_a_new_order_appears_in_the_list_and_the_totals(): void
    {
        $this->post(route('cashier.pos.store'), ['order_type' => 'take-out', 'items' => [['menu_item' => 'ensaymada', 'qty' => 2]]]);

        $this->get(route('cashier.orders.index'))->assertSee('-0014</b>', false)->assertSeeInOrder(['Orders today', '14']);
    }

    // ---- Row actions that move the order ------------------------------------------------------

    public function test_serving_a_ready_order_completes_it(): void
    {
        $this->patch(route('cashier.orders.complete', 6))->assertRedirect(route('cashier.orders.index', ['tab' => 'active']));

        $order = Order::find(6);
        $this->assertSame('completed', $order->status->value);
        $this->assertSame('completed', $order->statusLogs->last()->status->value);
        $this->assertSame(2, $order->statusLogs->last()->changed_by);
        $this->assertSame('Completed order', AuditLog::latest('id')->first()->action);

        $this->patch(route('cashier.orders.complete', 6))->assertForbidden(); // already done
        $this->patch(route('cashier.orders.complete', 8))->assertForbidden(); // still preparing
        $this->assertSame('preparing', Order::find(8)->status->value);
    }

    public function test_cancelling_an_unpaid_order_needs_a_reason(): void
    {
        $this->post(route('cashier.orders.cancel', 13), ['reason' => 'Because'])->assertSessionHasErrors('reason');
        $this->assertSame('awaiting_payment', Order::find(13)->status->value);

        $this->post(route('cashier.orders.cancel', 13), ['reason' => 'Duplicate order'])->assertRedirect();

        $order = Order::find(13);
        $this->assertSame(['cancelled', 'Duplicate order'], [$order->status->value, $order->cancel_reason]);
        $this->assertSame('Cancelled order', AuditLog::latest('id')->first()->action);

        $this->post(route('cashier.orders.cancel', 8), ['reason' => 'Duplicate order'])->assertForbidden(); // paid → void instead
    }

    public function test_voiding_a_paid_order_voids_the_payment_and_returns_the_stock(): void
    {
        // Order 8: 2× iced large oat latte + chicken adobo, paid by GCash and already deducted
        $oat = $this->stock('oat');
        $chicken = $this->stock('chicken');

        $this->post(route('cashier.orders.void', 8), ['reason' => 'Wrong item entered'])
            ->assertRedirect(route('cashier.orders.index', ['tab' => 'active']));

        $order = Order::find(8);
        $this->assertSame(['voided', 'Wrong item entered'], [$order->status->value, $order->cancel_reason]);
        $this->assertSame('VOIDED', $order->payment->status->value);
        $this->assertSame('Wrong item entered', $order->payment->void_reason);
        $this->assertNotNull($order->payment->voided_at);
        $this->assertFalse($order->stock_deducted);
        $this->assertSame($oat + 360, $this->stock('oat'));
        $this->assertSame($chicken + 150, $this->stock('chicken'));

        $returns = StockMovement::where('order_id', 8)->where('type', 'RETURN')->count();
        $this->assertGreaterThan(0, $returns);
        $this->assertSame('Voided order', AuditLog::latest('id')->first()->action);

        // Not twice, and not an unpaid or ready order
        $this->post(route('cashier.orders.void', 8), ['reason' => 'Duplicate order'])->assertForbidden();
        $this->post(route('cashier.orders.void', 13), ['reason' => 'Duplicate order'])->assertForbidden(); // unpaid → cancel instead
        $this->post(route('cashier.orders.void', 6), ['reason' => 'Duplicate order'])->assertForbidden();  // ready: no void
        $this->assertSame($oat + 360, $this->stock('oat'));
    }

    public function test_cash_on_delivery_cannot_be_marked_delivered_before_the_cash_is_in(): void
    {
        $this->get(route('cashier.orders.index'))->assertDontSee('Mark delivered');
        $this->patch(route('cashier.orders.complete', 9))->assertForbidden();
    }

    // ---- Payment -------------------------------------------------------------------------------

    public function test_payment_screen_for_an_unpaid_order(): void
    {
        $this->get(route('cashier.payments.create', 12))
            ->assertOk()
            ->assertSee('Senior citizen / PWD discount')
            ->assertSee('Confirm payment · ₱685.00')
            ->assertSee('Exact ₱685.00')
            ->assertSee('₱700');
    }

    public function test_payment_screen_explains_paid_and_cancelled_orders(): void
    {
        $this->get(route('cashier.payments.create', 8))->assertSee('Already paid')->assertSee('GC-');
        $this->get(route('cashier.payments.create', 5))->assertSee('This order was cancelled');
        $this->get(route('cashier.payments.create', 9))->assertSee('Collect COD payment');
    }

    public function test_cash_must_cover_the_amount_due(): void
    {
        $this->post(route('cashier.payments.store', 12), ['method' => 'CASH', 'amount_tendered' => 600])
            ->assertSessionHasErrors(['amount_tendered' => 'Amount received is short by ₱85.00.']);

        $this->assertNull(Order::find(12)->payment);
        $this->assertSame('awaiting_payment', Order::find(12)->status->value);
    }

    public function test_cash_payment_is_recorded_with_change_and_takes_the_stock_out(): void
    {
        $pudding = $this->stock('pudding');
        $fries = $this->stock('fries');

        $this->post(route('cashier.payments.store', 12), ['method' => 'CASH', 'amount_tendered' => 700])
            ->assertRedirect(route('cashier.receipt', 12));

        $order = Order::find(12);
        $this->assertSame('paid', $order->status->value);
        $this->assertNotNull($order->paid_at);
        $this->assertTrue($order->stock_deducted);

        $payment = $order->payment;
        $this->assertSame(['CASH', 685.0, 700.0, 15.0, 2], [$payment->method->value, $payment->amount, $payment->amount_tendered, $payment->change_due, $payment->received_by]);
        $this->assertSame('CS-'.now()->format('Ymd').'-0008', $payment->reference_no); // sample cash payments used up to 0007
        $this->assertSame($pudding - 3, $this->stock('pudding'));
        $this->assertSame($fries - 300, $this->stock('fries'));
        $this->assertSame(['awaiting_payment', 'paid'], $order->statusLogs->pluck('status')->map->value->all());

        $audit = AuditLog::where('action', 'Payment recorded')->latest('id')->first();
        $this->assertSame('Jessa Manalo', $audit->user_name);
        $this->assertStringContainsString($payment->reference_no, $audit->detail);

        $this->post(route('cashier.payments.store', 12), ['method' => 'GCASH'])->assertForbidden(); // not twice
        $this->assertSame(1, Order::find(12)->payment()->count());
    }

    public function test_an_e_wallet_payment_at_the_counter_changes_the_orders_method(): void
    {
        $this->post(route('cashier.payments.store', 13), ['method' => 'GCASH'])->assertRedirect(route('cashier.receipt', 13));

        $order = Order::find(13);
        $this->assertSame('GCASH', $order->payment_method->value);
        $this->assertStringStartsWith('GC-', $order->payment->reference_no);
        $this->assertNull($order->payment->amount_tendered);
    }

    public function test_senior_discount_lowers_the_amount_due_and_needs_the_id(): void
    {
        $discount = ['discount' => 1, 'discount_type' => 'Senior', 'eligible_count' => 1, 'diner_count' => 2];

        $this->post(route('cashier.payments.store', 12), $discount + ['method' => 'CASH', 'amount_tendered' => 600])
            ->assertSessionHasErrors(['id_no' => 'Enter the senior citizen or PWD ID number.', 'holder_name']);

        // ₱685, 1 of 2 diners → discount ₱97.86 → due ₱587.14, so ₱600 now covers it
        $this->post(route('cashier.payments.store', 12), $discount + ['id_no' => 'SC-2020-778899', 'holder_name' => 'Lola Ana', 'method' => 'CASH', 'amount_tendered' => 600])
            ->assertRedirect(route('cashier.receipt', 12));

        $order = Order::find(12);
        $this->assertSame([97.86, 587.14], [$order->discount_amount, $order->total]);
        $this->assertSame(587.14, $order->payment->amount);
        $this->assertSame(12.86, $order->payment->change_due);
        $this->assertSame([1, 2, 97.86], [$order->discount->eligible_count, $order->discount->diner_count, $order->discount->amount]);
        $this->assertSame(['SC-2020-778899', 'Lola Ana'], [$order->discount->id_no, $order->discount->holder_name]);

        // The ID and name are personal data: stored encrypted, and only the last four characters reach the audit log
        $raw = DB::table('discounts')->where('order_id', 12)->first();
        $this->assertStringNotContainsString('778899', $raw->id_no);
        $this->assertStringNotContainsString('Lola', $raw->holder_name);
        $audit = AuditLog::where('action', 'Applied discount')->latest('id')->first();
        $this->assertStringContainsString('••••8899', $audit->detail);
        $this->assertStringNotContainsString('SC-2020', $audit->detail);

        $this->post(route('cashier.payments.store', 13), ['discount' => 1, 'discount_type' => 'PWD', 'id_no' => 'P', 'holder_name' => 'X', 'eligible_count' => 3, 'diner_count' => 2, 'method' => 'GCASH'])
            ->assertSessionHasErrors('diner_count');
        $this->assertNull(Order::find(13)->discount);
    }

    public function test_collecting_cash_on_delivery_keeps_the_order_in_the_kitchen(): void
    {
        // Order 9: delivery, cash, already preparing, unpaid
        $this->post(route('cashier.payments.store', 9), ['method' => 'CASH', 'amount_tendered' => 1000])->assertRedirect(route('cashier.receipt', 9));

        $order = Order::find(9);
        $this->assertSame('preparing', $order->status->value, 'collecting the cash does not touch the kitchen');
        $this->assertNotNull($order->paid_at);
        $this->assertSame('PAID', $order->payment->status->value);
        $this->assertTrue($order->stock_deducted);
        $this->assertSame(['awaiting_payment', 'preparing'], $order->statusLogs->pluck('status')->map->value->all(), 'no extra "paid" step was logged');
    }

    public function test_payment_is_refused_when_the_order_cannot_be_made(): void
    {
        Ingredient::where('code', 'pudding')->update(['stock_qty' => 1]); // order 12 needs 3 egg puddings

        $this->post(route('cashier.payments.store', 12), ['method' => 'CASH', 'amount_tendered' => 700])
            ->assertRedirect()
            ->assertSessionHas('error');

        $order = Order::find(12);
        $this->assertSame('awaiting_payment', $order->status->value);
        $this->assertNull($order->payment);
        $this->assertNull($order->paid_at);
        $this->assertSame(1.0, $this->stock('pudding'));
    }

    public function test_paid_orders_cannot_be_paid_again(): void
    {
        $this->post(route('cashier.payments.store', 8), ['method' => 'GCASH'])->assertForbidden();
    }

    // ---- Receipt -------------------------------------------------------------------------------

    public function test_receipt_shows_discount_vat_and_change(): void
    {
        $this->get(route('cashier.receipt', 2))
            ->assertOk()
            ->assertSee('Senior disc. 20% (1/2)')
            ->assertSee('ID SC-2019-114523 · Rosario Santos')
            ->assertSeeInOrder(['VATable sales', '₱227.68', 'VAT (12%)', '₱27.32', 'VAT-exempt sales', '₱227.68'])
            ->assertSeeInOrder(['Give change', '₱562.86'])
            ->assertSee('data-print', false);
    }

    public function test_voided_receipt_is_stamped(): void
    {
        $this->get(route('cashier.receipt', 4))->assertSee('VOIDED · Customer changed their mind')->assertSee('VOIDED');
    }

    public function test_unknown_orders_are_not_found(): void
    {
        $this->get(route('cashier.receipt', 999))->assertNotFound();
        $this->get(route('cashier.payments.create', 999))->assertNotFound();
        $this->get(route('cashier.receipt', 'demo-0008'))->assertNotFound(); // staff screens use order numbers, not customer tokens
    }
}
