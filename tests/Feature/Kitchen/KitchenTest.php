<?php

namespace Tests\Feature\Kitchen;

use App\Models\AuditLog;
use App\Models\MenuItem;
use App\Models\Order;
use Tests\TestCase;

/** Kitchen display: board columns, ticket moves that really move the order, recall, sold-out switches. */
class KitchenTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->asKitchen();
    }

    private function stageOf(int $id): string
    {
        return Order::find($id)->status->value;
    }

    public function test_board_shows_kitchen_orders_in_their_columns(): void
    {
        // New: 0010 (paid) and 0011 (cash on delivery) · Preparing: 0008, 0009 · Ready: 0006, 0007
        $this->get(route('kitchen.display'))
            ->assertOk()
            ->assertSee('class="v-kitchen-display"', false)
            ->assertSeeInOrder(['New', '#0010', '#0011', 'Preparing', '#0008', '#0009', 'Ready', '#0006', '#0007'])
            ->assertDontSee('#0012') // unpaid counter cash: not in the kitchen yet
            ->assertDontSee('#0004'); // voided
    }

    public function test_tickets_show_where_items_options_notes_and_cod(): void
    {
        $this->get(route('kitchen.display'))
            ->assertSee('TABLE 3')
            ->assertSee('Iced · Large (16 oz) · Oat milk')
            ->assertSee('<span class="note">Less ice</span>', false)
            ->assertSee('Cash on delivery · rider collects')
            ->assertSee('In queue <b>6</b>', false)
            ->assertSee('Done today <b>3</b>', false)
            ->assertSee('data-start=', false);
    }

    public function test_each_column_has_its_buttons(): void
    {
        $this->get(route('kitchen.display'))
            ->assertSee('value="start">Start preparing', false)
            ->assertSee('value="ready">Mark ready', false)
            ->assertSee('value="back">Back', false)
            ->assertSee('value="done">Served', false)      // 0006 dine-in
            ->assertSee('value="done">Picked up', false)   // 0007 take-out
            ->assertSee('value="recall">Recall', false)
            ->assertSee('Recently done:');
    }

    public function test_moves_are_checked_against_the_stage(): void
    {
        $this->patch(route('kitchen.orders.status', 10), ['move' => 'start'])->assertRedirect(route('kitchen.display'));
        $this->assertSame('preparing', $this->stageOf(10));

        $this->patch(route('kitchen.orders.status', 11), ['move' => 'start'])->assertRedirect(); // COD goes to the kitchen unpaid
        $this->assertSame('preparing', $this->stageOf(11));

        $this->patch(route('kitchen.orders.status', 8), ['move' => 'ready'])->assertRedirect();
        $this->assertSame('ready', $this->stageOf(8));

        $this->patch(route('kitchen.orders.status', 9), ['move' => 'back'])->assertRedirect(); // COD: back to unpaid
        $this->assertSame('awaiting_payment', $this->stageOf(9));
        $this->patch(route('kitchen.orders.status', 10), ['move' => 'back'])->assertRedirect(); // paid: back to paid
        $this->assertSame('paid', $this->stageOf(10));

        $this->patch(route('kitchen.orders.status', 6), ['move' => 'done'])->assertRedirect();
        $this->assertSame('completed', $this->stageOf(6));
        $this->patch(route('kitchen.orders.status', 1), ['move' => 'recall'])->assertRedirect(); // completed → ready
        $this->assertSame('ready', $this->stageOf(1));
        $this->patch(route('kitchen.orders.status', 7), ['move' => 'recall'])->assertRedirect(); // ready → preparing
        $this->assertSame('preparing', $this->stageOf(7));

        $this->patch(route('kitchen.orders.status', 12), ['move' => 'start'])->assertForbidden(); // not paid
        $this->patch(route('kitchen.orders.status', 10), ['move' => 'done'])->assertForbidden();  // not ready
        $this->patch(route('kitchen.orders.status', 5), ['move' => 'recall'])->assertForbidden(); // cancelled
        $this->patch(route('kitchen.orders.status', 8), ['move' => 'fly'])->assertSessionHasErrors('move');
        $this->patch(route('kitchen.orders.status', 999), ['move' => 'start'])->assertNotFound();
        $this->assertSame('awaiting_payment', $this->stageOf(12));
    }

    public function test_every_move_is_logged_and_audited(): void
    {
        $this->patch(route('kitchen.orders.status', 10), ['move' => 'start']);
        $this->patch(route('kitchen.orders.status', 10), ['move' => 'ready']);

        $order = Order::find(10);
        $this->assertSame(['awaiting_payment', 'paid', 'preparing', 'ready'], $order->statusLogs->pluck('status')->map->value->all());
        $this->assertSame(4, $order->statusLogs->last()->changed_by, 'Ramon Cruz in the kitchen');
        $this->assertSame(['Marked ready', 'Started preparing'], AuditLog::orderByDesc('id')->limit(2)->pluck('action')->all());
        $this->assertSame('kitchen', AuditLog::latest('id')->first()->role);
    }

    public function test_an_unpaid_cod_order_cannot_be_served_until_the_cash_is_collected(): void
    {
        $this->patch(route('kitchen.orders.status', 11), ['move' => 'start']);
        $this->patch(route('kitchen.orders.status', 11), ['move' => 'ready']);
        $this->patch(route('kitchen.orders.status', 11), ['move' => 'done'])->assertForbidden();

        $this->assertSame('ready', $this->stageOf(11));
    }

    public function test_board_partial_is_served_for_the_refresh(): void
    {
        $this->get(route('kitchen.board'))
            ->assertOk()
            ->assertSee('data-k-part="stats"', false)
            ->assertSee('data-k-part="columns"', false)
            ->assertSee('data-k-part="recent"', false)
            ->assertDontSee('<html', false);
    }

    public function test_a_quiet_night_shows_friendly_empty_columns(): void
    {
        $html = view('kitchen.partials.board', [
            'columns' => ['new' => collect(), 'prep' => collect(), 'ready' => collect()],
            'stats' => ['queue' => 0, 'avgPrep' => '–', 'done' => 0],
            'recent' => collect(),
        ])->render();

        $this->assertStringContainsString('No new orders. New paid orders appear here.', $html);
        $this->assertStringContainsString('Nothing in progress.', $html);
        $this->assertStringContainsString('Nothing waiting to be served.', $html);
        $this->assertStringContainsString('Finished orders will appear here.', $html);
        $this->assertStringContainsString('Avg prep today <b>–</b>', $html);
    }

    public function test_sold_out_drawer_lists_every_item_as_a_switch(): void
    {
        $this->get(route('kitchen.display'))->assertSee('data-drawer hidden', false)->assertSee('Taro Milk Tea · sold out');
        $this->get(route('kitchen.display', ['drawer' => 1]))->assertDontSee('data-drawer hidden', false);
    }

    public function test_the_kitchen_can_mark_an_item_sold_out_and_available_again(): void
    {
        $this->patch(route('kitchen.availability.update', 'latte'), ['available' => 0])
            ->assertRedirect(route('kitchen.display', ['drawer' => 1]));

        $this->assertTrue(MenuItem::where('code', 'latte')->value('sold_out_override'));
        $this->assertSame('Marked sold out', AuditLog::latest('id')->first()->action);

        // Customers and the POS see it at once
        $this->withSession(['qr' => ['type' => 'dine-in', 'table' => 1]])->get(route('customer.menu'))
            ->assertSee('data-item="latte" data-search="café latte', false)->assertDontSee('<template id="item-latte"', false);
        $this->post(route('customer.cart.store'), ['menu_item' => 'latte', 'qty' => 1, 'options' => ['temp' => 'hot', 'size' => 'm']])
            ->assertSessionHasErrors(['menu_item' => 'Sorry, Café Latte just sold out.']);

        $this->patch(route('kitchen.availability.update', 'latte'), ['available' => 1])->assertRedirect();
        $this->assertNull(MenuItem::where('code', 'latte')->value('sold_out_override'));
        $this->assertSame('Marked available', AuditLog::latest('id')->first()->action);

        $this->patch(route('kitchen.availability.update', 'pizza'), ['available' => 0])->assertNotFound();
        $this->patch(route('kitchen.availability.update', 'latte'), [])->assertSessionHasErrors('available');
    }
}
