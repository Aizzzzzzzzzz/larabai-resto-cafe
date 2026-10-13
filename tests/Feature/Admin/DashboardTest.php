<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\View\Components\BarChart;
use Database\Seeders\SampleHistorySeeder;
use Tests\TestCase;

/** Admin dashboard: KPIs and charts computed from the orders and payments tables, best sellers, activity, attention list. */
class DashboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->asAdmin();
    }

    /** The 30 days before today, as finished orders. */
    private function withHistory(): void
    {
        $this->seed(SampleHistorySeeder::class);
    }

    public function test_dashboard_shows_the_figures_and_charts(): void
    {
        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSeeInOrder(['Good ', 'Maria'])
            ->assertSeeInOrder(['Sales today', 'Paid orders today', 'Average order', 'Senior/PWD discounts today'])
            ->assertSeeInOrder(['Sales, last 14 days', 'Payment methods', 'Last 30 days', 'Today', 'Orders by hour', 'Best sellers', 'Recent activity', 'Needs attention'])
            ->assertSee('Daily sales for the last 14 days')
            ->assertSee('class="share-bar"', false);
    }

    public function test_todays_figures_come_from_the_paid_orders(): void
    {
        // Today's paid orders that weren't voided: 1, 2, 3, 6, 7, 8, 10 (9 is cash on delivery and not collected yet)
        $paid = Order::whereNotNull('paid_at')->where('status', '!=', 'voided');
        $total = (float) $paid->sum('total');
        $this->assertSame(7, $paid->count());

        $this->get(route('admin.dashboard'))
            ->assertSee(peso($total))
            ->assertSee(peso($total / 7))
            ->assertSee(peso(72.86)); // the Senior discount on order 2
    }

    public function test_a_new_payment_shows_up_at_once(): void
    {
        $before = (float) Order::whereNotNull('paid_at')->where('status', '!=', 'voided')->sum('total');
        $this->post(route('login.store'), ['username' => 'cashier', 'password' => 'cashier123']);
        $this->post(route('cashier.payments.store', 12), ['method' => 'GCASH']);
        $this->asAdmin();

        $this->get(route('admin.dashboard'))->assertSee(peso($before + 685));
    }

    public function test_voided_orders_do_not_count_as_sales(): void
    {
        $before = (float) Order::whereNotNull('paid_at')->where('status', '!=', 'voided')->sum('total');

        $this->asCashier()->post(route('cashier.orders.void', 8), ['reason' => 'Duplicate order']);

        $this->asAdmin()->get(route('admin.dashboard'))->assertSee(peso($before - Order::find(8)->total));
    }

    public function test_sales_chart_has_fourteen_bars_with_today_highlighted(): void
    {
        $this->withHistory();
        $html = $this->get(route('admin.dashboard'))->getContent();

        $this->assertMatchesRegularExpression('/aria-label="Daily sales for the last 14 days"(.*?)<\/svg>/s', $html);
        preg_match('/aria-label="Daily sales for the last 14 days"(.*?)<\/svg>/s', $html, $m);

        $this->assertSame(14, substr_count($m[1], '<g class="bar'));
        $this->assertSame(1, substr_count($m[1], 'class="bar hl"'));
        $this->assertSame(1, preg_match('/class="bar hl".*?<title>[^<]*orders<\/title>/s', $m[1]));
    }

    public function test_hours_chart_covers_opening_hours(): void
    {
        $this->withHistory();
        $html = $this->get(route('admin.dashboard'))->getContent();
        preg_match('/aria-label="Average orders per hour"(.*?)<\/svg>/s', $html, $m);

        $this->assertSame(15, substr_count($m[1], '<g class="bar')); // 7 AM to 9 PM
        $this->assertSame(1, substr_count($m[1], 'class="bar hl"'));  // the busiest hour
    }

    public function test_needs_attention_links_to_where_each_thing_is_handled(): void
    {
        $this->get(route('admin.dashboard'))
            ->assertSee('href="'.route('cashier.orders.index').'"', false)
            ->assertSee('href="'.route('kitchen.display').'"', false)
            ->assertSee('href="'.route('inventory.ingredients.index').'"', false)
            ->assertSee('Taro powder')           // an ingredient that's out
            ->assertSee('Taro Milk Tea');        // the item that can't be made
    }

    public function test_recent_activity_comes_from_the_audit_log(): void
    {
        $this->get(route('admin.dashboard'))->assertSee('Payment recorded')->assertSee('See the full audit log');
    }

    public function test_history_feeds_the_payment_mix_best_sellers_and_the_weekly_comparison(): void
    {
        $this->withHistory();

        $this->get(route('admin.dashboard'))
            ->assertSee('vs this time last week')
            ->assertDontSee('No sales in the last 7 days yet.')
            ->assertSee('Café Latte'); // the sample's most popular item

        $this->assertGreaterThan(3000, Order::count(), 'about a month of orders');
    }

    public function test_only_the_owner_sees_the_dashboard(): void
    {
        foreach (['cashier', 'kitchen', 'stock'] as $username) {
            $this->signIn($username)->get(route('admin.dashboard'))->assertForbidden();
        }
    }

    public function test_a_quiet_week_renders_instead_of_crashing(): void
    {
        // The day a brand-new café has no sales at all: every empty state in one render
        $html = view('admin.dashboard', [
            'greeting' => 'Good morning, Maria',
            'kpis' => ['sales' => 0, 'salesBefore' => 0, 'orders' => 0, 'ordersBefore' => 0, 'average' => 0, 'averageBefore' => 0, 'discounts' => 0],
            'salesChart' => array_map(fn ($i) => ['label' => "Day {$i}", 'value' => 0, 'tip' => ''], range(1, 14)),
            'mixLast30' => [['label' => 'GCash', 'color' => 'red', 'value' => 0], ['label' => 'Cash', 'color' => 'blue', 'value' => 0]],
            'mixToday' => null,
            'hoursChart' => array_map(fn ($h) => ['label' => "{$h}a", 'value' => 0, 'tip' => ''], range(7, 21)),
            'best' => [],
            'activity' => collect(),
            'attention' => [],
        ])->render();

        $this->assertStringContainsString('No sales in the last 7 days yet.', $html);
        $this->assertStringContainsString('No paid orders yet today.', $html);
        $this->assertStringContainsString('No activity yet.', $html);
        $this->assertStringContainsString('₱0.00', $html);
    }

    public function test_nice_axis_maximums(): void
    {
        $this->assertSame(1.0, BarChart::niceMax(0));
        $this->assertSame(1.0, BarChart::niceMax(1));
        $this->assertSame(2.0, BarChart::niceMax(1.4));
        $this->assertSame(25000.0, BarChart::niceMax(22000));
        $this->assertSame(50000.0, BarChart::niceMax(41500));
        $this->assertSame(100000.0, BarChart::niceMax(55602));
    }
}
