<?php

namespace Tests\Feature\Kitchen;

use App\Http\Controllers\Controller;
use Tests\TestCase;

/** UI scaffold Step 4 — kitchen display: board columns, ticket moves, recall, sold-out switches. */
class KitchenTest extends TestCase
{
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
        $this->patch(route('kitchen.orders.status', 10), ['move' => 'start'])->assertRedirect(route('kitchen.display'))->assertSessionHas('status', Controller::DEMO_MESSAGE);
        $this->patch(route('kitchen.orders.status', 11), ['move' => 'start'])->assertRedirect(); // COD goes to the kitchen unpaid
        $this->patch(route('kitchen.orders.status', 8), ['move' => 'ready'])->assertRedirect();
        $this->patch(route('kitchen.orders.status', 8), ['move' => 'back'])->assertRedirect();
        $this->patch(route('kitchen.orders.status', 6), ['move' => 'done'])->assertRedirect();
        $this->patch(route('kitchen.orders.status', 1), ['move' => 'recall'])->assertRedirect(); // completed → ready

        $this->patch(route('kitchen.orders.status', 12), ['move' => 'start'])->assertForbidden(); // not paid
        $this->patch(route('kitchen.orders.status', 10), ['move' => 'done'])->assertForbidden();  // not ready
        $this->patch(route('kitchen.orders.status', 5), ['move' => 'recall'])->assertForbidden(); // cancelled
        $this->patch(route('kitchen.orders.status', 8), ['move' => 'fly'])->assertSessionHasErrors('move');
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

    public function test_sold_out_drawer_lists_every_item_as_a_switch(): void
    {
        $this->get(route('kitchen.display'))->assertSee('data-drawer hidden', false)->assertSee('Taro Milk Tea · sold out');
        $this->get(route('kitchen.display', ['drawer' => 1]))->assertDontSee('data-drawer hidden', false);

        $this->patch(route('kitchen.availability.update', 'latte'), ['available' => 0])
            ->assertRedirect(route('kitchen.display', ['drawer' => 1]))
            ->assertSessionHas('status', Controller::DEMO_MESSAGE);
        $this->patch(route('kitchen.availability.update', 'pizza'), ['available' => 0])->assertNotFound();
        $this->patch(route('kitchen.availability.update', 'latte'), [])->assertSessionHasErrors('available');
    }
}
