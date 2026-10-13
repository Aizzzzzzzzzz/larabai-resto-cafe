<?php

namespace Tests\Feature\Public;

use App\Services\QrCodes;
use App\Support\Showcase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/** UI scaffold Step 7 — the public homepage and the demo system navigator. */
class HomeAndNavigatorTest extends TestCase
{
    // ---- Homepage ------------------------------------------------------------------------------

    public function test_homepage_tells_the_story_and_how_to_order(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('class="v-home-index"', false)
            ->assertSee('Good coffee.')
            ->assertSeeInOrder(['Our story', 'Customer favorites', 'Order at your table', 'Grab a table', 'Scan and order', 'Pay your way', 'Visit'])
            ->assertSee('meta name="description"', false)
            ->assertSee('F. Tañedo St., San Nicolas, Tarlac City, Tarlac')
            ->assertSee('0917 555 0142')
            ->assertSee('20% off for senior citizens and PWDs');
    }

    public function test_homepage_numbers_match_the_menu_and_the_tables(): void
    {
        // 27 menu items; five tables seating 2 + 2 + 4 + 4 + 6 (document.md §1) — not the mockup's invented 14 / 44
        $this->get(route('home'))
            ->assertSee('<strong>27</strong><span>items on the menu</span>', false)
            ->assertSee('<strong>5</strong><span>tables, 18 seats</span>', false)
            ->assertSee('Today we have 5 tables');
    }

    public function test_the_glass_shows_the_first_best_seller_and_carries_all_three(): void
    {
        $html = $this->get(route('home'))
            ->assertSee('role="tab"', false)
            ->assertSeeInOrder(['Spanish Latte', 'Matcha Latte', 'Brown Sugar'])
            ->assertSeeInOrder(['Condensed milk', '30 ml', 'Fresh milk', '150 ml', 'Espresso', '2 shots', 'Milk foam'])
            ->assertSee('₱160')
            ->assertSee('Iced · our best seller')
            ->getContent();

        $this->assertSame(3, substr_count($html, 'role="tab"'));
        $this->assertSame(1, substr_count($html, 'aria-selected="true"'));
        $this->assertSame(4, substr_count($html, 'class="layer'));
        $this->assertMatchesRegularExpression('/data-cups="[^"]*&quot;Brown Sugar&quot;/', $html);
    }

    public function test_cup_prices_follow_the_menu(): void
    {
        $this->assertSame(160, Showcase::cups()[0]['price']);
        $this->assertSame(['Spanish Latte', 'Matcha Latte', 'Brown Sugar Pearl Milk'], array_column(Showcase::cups(), 'name'));
    }

    public function test_favorites_are_best_sellers_and_new_items_that_can_be_ordered(): void
    {
        $this->get(route('home'))
            ->assertSee('Café Latte')->assertSee('Beef Tapa')           // best sellers
            ->assertSee('Sea Salt Latte')->assertSee('Ube Latte')       // new
            ->assertDontSee('Americano')                                // neither
            ->assertSee('class="chip chip-gold">Best seller', false)
            ->assertSee('class="chip chip-green">New', false)
            ->assertSee('mini-glass', false)->assertSee('class="plate"', false);
    }

    public function test_homepage_works_without_demo_mode(): void
    {
        config(['cafe.demo_mode' => false]);

        $this->get(route('home'))->assertOk()->assertDontSee('navigator');
    }

    // ---- Navigator -----------------------------------------------------------------------------

    public function test_navigator_shows_live_counts_and_the_walkthrough(): void
    {
        $this->get(route('navigator'))
            ->assertOk()
            ->assertSee('class="v-index-index"', false)
            ->assertSeeInOrder(['13', 'orders today', '4', 'awaiting payment', '5', 'in the kitchen', '5', 'low on stock'])
            ->assertSeeInOrder(['Order as a customer', 'Take the payment', 'cashier / cashier123', 'Prepare and serve it', 'kitchen / kitchen123', 'Check the stock it used', 'stock / stock123', 'See it in the sales report', 'admin / admin123']);
    }

    public function test_the_floor_plan_opens_each_table_with_its_signed_qr_link(): void
    {
        $html = $this->get(route('navigator'))->getContent();

        $this->assertSame(6, substr_count($html, 'class="fp"'));
        foreach (range(1, 5) as $table) {
            $this->assertStringContainsString('href="'.e(QrCodes::tableUrl($table)).'"', $html, "table {$table}");
        }
        $this->assertStringContainsString('href="'.e(QrCodes::counterUrl()).'"', $html);
        $this->assertStringNotContainsString('?table=', $html);

        // Table 1 has an open order in the sample data (0010), table 2 doesn't
        $this->assertStringContainsString('aria-label="Table 1, 2 seats, 1 open order"', $html);
        $this->assertStringContainsString('aria-label="Table 2, 2 seats, free"', $html);
        $this->assertStringContainsString('aria-label="Table 5, 6 seats, 1 open order"', $html);
        $this->assertStringContainsString('aria-label="Take-out and delivery, 4 open orders"', $html);
    }

    public function test_following_a_floor_plan_link_is_the_same_as_scanning(): void
    {
        $this->get(QrCodes::tableUrl(2))->assertRedirect(route('customer.menu'))->assertSessionHas('qr', ['type' => 'dine-in', 'table' => 2]);
        $this->assertTrue(URL::hasValidSignature(request()->create(QrCodes::tableUrl(2))));
    }

    public function test_every_role_card_lists_its_screens_and_demo_login(): void
    {
        $this->get(route('navigator'))
            ->assertSeeInOrder(['Public', 'Café homepage', 'Staff login', 'Customer', 'Menu', 'Cart', 'Checkout', 'Cashier', 'Demo login', 'cashier / cashier123', 'Point of sale', 'Orders and payments', 'Kitchen', 'Kitchen display and sold-out switches', 'Inventory', 'Stock, low-stock alerts and restocking', 'Admin', 'admin / admin123', 'Sales dashboard', 'Menu and prices', 'Users and roles', 'Audit logs', 'Table QR codes'])
            ->assertSee('Opens from checkout')->assertSee('Opens from Orders')->assertSee('Opens after payment')
            ->assertSee('4 unpaid')->assertSee('5 in queue')->assertSee('5 low')
            ->assertSee('href="'.route('cashier.pos.index').'"', false)
            ->assertSee('<span class="path">/cashier/pos</span>', false);
    }

    public function test_navigator_links_the_reset_page_and_exists_only_in_demo_mode(): void
    {
        $this->get(route('navigator'))->assertSee('href="'.route('admin.demo-reset.show').'"', false);

        config(['cafe.demo_mode' => false]);
        $this->get(route('navigator'))->assertNotFound();
    }

    public function test_floor_plan_shapes_follow_the_seats(): void
    {
        $this->assertSame('round2', \App\Support\FloorPlan::table(2)['shape']);
        $this->assertSame('square4', \App\Support\FloorPlan::table(4)['shape']);
        $this->assertSame('long6', \App\Support\FloorPlan::table(6)['shape']);
        // A two-seater has two chairs (a seat square each) around one round table
        $this->assertSame(2, substr_count(\App\Support\FloorPlan::table(2)['svg'], 'rx="3"') - 1); // minus the QR card
        $this->assertSame(4, substr_count(\App\Support\FloorPlan::table(4)['svg'], 'width="12"') + substr_count(\App\Support\FloorPlan::table(4)['svg'], 'height="12"')); // four seats
        $this->assertStringContainsString('<circle', \App\Support\FloorPlan::rider());
    }
}
