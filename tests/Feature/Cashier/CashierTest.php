<?php

namespace Tests\Feature\Cashier;

use App\Http\Controllers\Controller;
use Tests\TestCase;

/**
 * UI scaffold Step 3 — cashier module: POS charge (validated, re-priced), orders list with
 * stage-specific actions, payment with Senior/PWD discount, receipt. Nothing is saved.
 */
class CashierTest extends TestCase
{
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

    public function test_charge_posts_the_ticket_and_goes_to_payment(): void
    {
        $this->post(route('cashier.pos.store'), [
            'order_type' => 'dine-in',
            'table_no' => 4,
            'items' => [
                ['menu_item' => 'latte', 'qty' => 2, 'options' => ['temp' => ['iced'], 'size' => ['l']]],
                ['menu_item' => 'ensaymada', 'qty' => 1],
            ],
        ])->assertRedirect(route('cashier.payments.create', 12))
            ->assertSessionHas('status', Controller::DEMO_MESSAGE)
            ->assertSessionHas('pos_reset', true);

        // The next POS visit empties the browser-kept ticket once
        $this->get(route('cashier.pos.index'))->assertSee('data-reset', false);
        $this->get(route('cashier.pos.index'))->assertDontSee('data-reset', false);
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

    public function test_row_actions_follow_the_order_stage(): void
    {
        $this->patch(route('cashier.orders.complete', 6))->assertRedirect()->assertSessionHas('status', Controller::DEMO_MESSAGE);
        $this->patch(route('cashier.orders.complete', 8))->assertForbidden(); // still preparing

        $reason = ['reason' => 'Duplicate order'];
        $this->post(route('cashier.orders.cancel', 13), $reason)->assertRedirect();
        $this->post(route('cashier.orders.cancel', 8), $reason)->assertForbidden(); // paid → void instead
        $this->post(route('cashier.orders.void', 8), $reason)->assertRedirect();
        $this->post(route('cashier.orders.void', 13), $reason)->assertForbidden(); // unpaid → cancel instead
        $this->post(route('cashier.orders.void', 6), $reason)->assertForbidden();  // ready: no void (mockup)

        $this->post(route('cashier.orders.void', 8), ['reason' => 'Because'])->assertSessionHasErrors('reason');
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

        $this->post(route('cashier.payments.store', 12), ['method' => 'CASH', 'amount_tendered' => 700])
            ->assertRedirect(route('cashier.receipt', 12))
            ->assertSessionHas('status', Controller::DEMO_MESSAGE);

        $this->post(route('cashier.payments.store', 12), ['method' => 'GCASH'])->assertRedirect(route('cashier.receipt', 12));
    }

    public function test_senior_discount_lowers_the_amount_due_and_needs_the_id(): void
    {
        $discount = ['discount' => 1, 'discount_type' => 'Senior', 'eligible_count' => 1, 'diner_count' => 2];

        $this->post(route('cashier.payments.store', 12), $discount + ['method' => 'CASH', 'amount_tendered' => 600])
            ->assertSessionHasErrors(['id_no' => 'Enter the senior citizen or PWD ID number.', 'holder_name']);

        // ₱685, 1 of 2 diners → discount ₱97.86 → due ₱587.14, so ₱600 now covers it
        $this->post(route('cashier.payments.store', 12), $discount + ['id_no' => 'SC-1', 'holder_name' => 'Lola Ana', 'method' => 'CASH', 'amount_tendered' => 600])
            ->assertRedirect(route('cashier.receipt', 12));

        $this->post(route('cashier.payments.store', 12), ['discount' => 1, 'discount_type' => 'PWD', 'id_no' => 'P', 'holder_name' => 'X', 'eligible_count' => 3, 'diner_count' => 2, 'method' => 'GCASH'])
            ->assertSessionHasErrors('diner_count');
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
}
