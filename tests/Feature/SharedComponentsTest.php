<?php

namespace Tests\Feature;

use App\Support\DemoData;
use Tests\TestCase;

/** UI scaffold Step 1: each shared component renders the mockup's markup. */
class SharedComponentsTest extends TestCase
{
    public function test_status_pill_uses_the_mockup_label_and_chip(): void
    {
        $this->blade('<x-status-pill status="paid" />')
            ->assertSee('<span class="chip chip-gcash">Order received</span>', false);

        $this->blade('<x-status-pill status="voided" label="BB-0004" />')
            ->assertSee('<span class="chip chip-red">BB-0004</span>', false);
    }

    public function test_peso_formats_like_the_mockup(): void
    {
        $this->assertSame('₱1,234.50', peso(1234.5));
        $this->assertSame('₱1,235', peso(1234.5, false));
        $this->assertSame('₱0.00', peso(null));
    }

    public function test_order_lines_show_options_note_and_line_amount(): void
    {
        $lines = DemoData::order(8)['items']; // 2× iced large latte with oat milk, note "Less ice"

        $this->blade('<x-order-lines :lines="$lines" />', ['lines' => $lines])
            ->assertSeeInOrder(['2×', 'Café Latte', 'Iced · Large (16 oz) · Oat milk', 'Note: Less ice', '₱410.00']);
    }

    public function test_modal_holds_a_real_form_with_csrf_and_method_spoofing(): void
    {
        $this->blade('<x-modal id="edit" title="Edit" action="/admin/users/1" method="PUT">Fields</x-modal>')
            ->assertSee('id="edit" hidden', false)
            ->assertSee('name="_token"', false)
            ->assertSee('name="_method" value="PUT"', false)
            ->assertSee('name="_modal" value="edit"', false)
            ->assertDontSee('data-modal-autoopen', false);
    }

    public function test_modal_reopens_when_its_form_failed_validation(): void
    {
        // As after a failed submit: errors shared with the views, old input in the session
        $this->withViewErrors(['name' => 'The name field is required.']);
        $this->app['request']->setLaravelSession($this->app['session.store']);
        session()->flashInput(['_modal' => 'edit']);

        $this->blade('<x-modal id="edit" title="Edit" action="/x"><x-field-error name="name" /></x-modal>')
            ->assertSee('data-modal-autoopen', false)
            ->assertSee('The name field is required.');

        $this->blade('<x-modal id="other" title="Other" action="/x">Fields</x-modal>')
            ->assertDontSee('data-modal-autoopen', false);
    }

    public function test_stat_card_and_delta(): void
    {
        $this->blade('<x-stat-card label="Sales today" value="₱120" tone="alert"><x-delta :now="120" :before="100" /></x-stat-card>')
            ->assertSee('class="kpi alert"', false)
            ->assertSee('▲ 20% vs this time last week');

        $this->blade('<x-delta :now="80" :before="100" />')->assertSee('▼ 20%');
        $this->blade('<x-delta :now="0" :before="100" />')->assertSee('No paid orders yet today');
    }

    public function test_tracker_follows_the_order_stage(): void
    {
        // Dine-in, paid by GCash, now preparing
        $this->blade('<x-tracker :order="$o" />', ['o' => DemoData::order(8)])
            ->assertSeeInOrder(['class="done"', 'Order placed', 'class="done"', 'Payment confirmed', 'GCash · GC-', 'class="now"', 'Preparing', 'class="todo"', 'Ready', 'class="todo"', 'Served to your table'], false);

        // Cash on delivery in the kitchen: the payment step waits for the rider
        $this->blade('<x-tracker :order="$o" />', ['o' => DemoData::order(9)])
            ->assertSeeInOrder(['class="todo"', 'Pay the rider', 'class="now"', 'Preparing', 'Out for delivery', 'Delivered'], false);

        // Cancelled: two steps with the reason
        $this->blade('<x-tracker :order="$o" />', ['o' => DemoData::order(5)])
            ->assertSeeInOrder(['Order placed', 'class="now"', 'Cancelled', 'Cancelled by customer'], false)
            ->assertDontSee('Preparing');
    }

    public function test_art_draws_a_glass_for_drinks_and_a_plate_for_food(): void
    {
        $this->blade('<x-art :item="$i" />', ['i' => DemoData::item('latte')])->assertSee('art-glass', false);
        $this->blade('<x-art :item="$i" />', ['i' => DemoData::item('tapa')])->assertSee('art-plate', false);
    }

    public function test_confirm_form_asks_for_the_word(): void
    {
        $this->blade('<x-confirm-form action="/admin/demo-reset" word="RESET" button="Reset">Explain</x-confirm-form>')
            ->assertSee('data-confirm-word="RESET"', false)
            ->assertSee('Type RESET to confirm')
            ->assertSee('name="confirm"', false)
            ->assertSee('name="_token"', false);
    }

    public function test_empty_states(): void
    {
        $this->blade('<x-empty-state title="No orders yet" icon="receipt" />')->assertSee('class="empty"', false)->assertSee('No orders yet');
        $this->blade('<x-empty-row colspan="6">No ingredients match.</x-empty-row>')
            ->assertSee('<td colspan="6" class="empty-row">No ingredients match.</td>', false);
    }
}
