<?php

namespace Tests\Feature\Admin;

use App\View\Components\BarChart;
use Tests\TestCase;

/** UI scaffold Step 6 — admin dashboard: KPIs, charts, best sellers, activity, attention list. */
class DashboardTest extends TestCase
{
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

    public function test_sales_chart_has_fourteen_bars_with_today_highlighted(): void
    {
        $html = $this->get(route('admin.dashboard'))->getContent();

        $this->assertMatchesRegularExpression('/aria-label="Daily sales for the last 14 days"(.*?)<\/svg>/s', $html);
        preg_match('/aria-label="Daily sales for the last 14 days"(.*?)<\/svg>/s', $html, $m);

        $this->assertSame(14, substr_count($m[1], '<g class="bar'));
        $this->assertSame(1, substr_count($m[1], 'class="bar hl"'));
        $this->assertSame(1, preg_match('/class="bar hl".*?<title>[^<]*orders<\/title>/s', $m[1]));
    }

    public function test_hours_chart_covers_opening_hours(): void
    {
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
