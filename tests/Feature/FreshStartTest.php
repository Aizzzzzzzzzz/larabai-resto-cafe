<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Catalog;
use Database\Seeders\DatabaseSeeder;
use Tests\TestCase;

/** CAFE_SAMPLE_ORDERS=false: the accounts, menu, stock and tables, but a clean slate for orders. */
class FreshStartTest extends TestCase
{
    protected bool $seedSampleData = false;

    public function test_a_fresh_start_has_accounts_and_menu_but_no_orders(): void
    {
        config(['cafe.sample_orders' => false]);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(5, User::count());
        $this->assertCount(27, Catalog::items());
        $this->assertCount(41, Catalog::ingredients());
        $this->assertCount(5, Catalog::tables());
        $this->assertSame(0, Order::count());

        // Stock starts from a count, one movement per ingredient with stock, and the books add up
        $this->assertSame(40, StockMovement::where('note', 'Opening stock')->count()); // taro is at 0
        $this->assertSame(0, StockMovement::where('type', '!=', 'ADJUST')->count());

        // The demo accounts still work, and every screen opens on an empty day
        $this->signIn('admin')->get(route('admin.dashboard'))->assertOk();
        $this->get(route('cashier.orders.index'))->assertOk()->assertSee('No orders waiting for payment.');
        $this->get(route('kitchen.display'))->assertOk()->assertSee('No new orders.');
    }
}
