<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\CashierActions;
use App\Support\CurrentStaff;
use App\Support\DemoAudit;
use App\Support\DemoData;
use App\Support\DemoSales;
use App\Support\Format;
use App\Support\StockLevel;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Sales dashboard (document.md §3.7). Everything is computed from "paid, not voided" orders;
 * UI scaffold: generated history for the 30 days before today plus today's sample orders.
 * Phase 7 runs the same figures as queries.
 */
class DashboardController extends Controller
{
    private const PAYMENT_COLORS = ['GCASH' => 'var(--pay-gcash)', 'CASH' => 'var(--pay-cash)', 'MAYA' => 'var(--pay-maya)'];

    private const PAYMENT_NAMES = ['GCASH' => 'GCash', 'CASH' => 'Cash', 'MAYA' => 'Maya'];

    public function index(): View
    {
        $day = fn (int $ago) => now()->startOfDay()->subDays($ago);
        $sum = fn (array $orders, string $key) => array_sum(array_column($orders, $key));

        $today = DemoSales::between($day(0), $day(-1));
        // The same weekday last week, up to the same time of day, so the comparison is fair mid-shift
        $lastWeek = DemoSales::between($day(7), now()->subDays(7));
        $week = DemoSales::between($day(7), $day(0));

        $salesToday = $sum($today, 'total');
        $salesLastWeek = $sum($lastWeek, 'total');

        return view('admin.dashboard', [
            'greeting' => $this->greeting(),
            'kpis' => [
                'sales' => $salesToday, 'salesBefore' => $salesLastWeek,
                'orders' => count($today), 'ordersBefore' => count($lastWeek),
                'average' => $today ? $salesToday / count($today) : 0,
                'averageBefore' => $lastWeek ? $salesLastWeek / count($lastWeek) : 0,
                'discounts' => $sum($today, 'discount'),
            ],
            'salesChart' => $this->salesByDay($day, $sum),
            'mixLast30' => $this->paymentMix(DemoSales::between($day(30), $day(-1))),
            'mixToday' => $today ? $this->paymentMix($today) : null,
            'hoursChart' => $this->ordersByHour($week),
            'best' => $this->bestSellers([...$week, ...$today]),
            'activity' => collect(DemoAudit::all())->reverse()->take(8)->map(fn ($a) => $a + ['ago' => Format::ago($a['logged_at'])])->values(),
            'attention' => $this->attention(),
        ]);
    }

    private function greeting(): string
    {
        $hour = (int) now()->format('G');
        $part = $hour < 12 ? 'morning' : ($hour < 18 ? 'afternoon' : 'evening');

        return "Good {$part}, ".explode(' ', CurrentStaff::get()['name'])[0];
    }

    /** Sales for each of the last 14 days, today last (highlighted). */
    private function salesByDay(callable $day, callable $sum): array
    {
        $data = [];
        for ($i = 13; $i >= 0; $i--) {
            $orders = DemoSales::between($day($i), $day($i - 1));
            $date = $day($i);
            $data[] = [
                'label' => $date->format('M j'),
                'value' => $sum($orders, 'total'),
                'tip' => '<b>'.$date->format('D, M j').'</b><span>'.peso($sum($orders, 'total')).' · '.count($orders).' orders</span>',
            ];
        }

        return $data;
    }

    /** Share of sales by payment method, in the order GCash, Cash, Maya. */
    private function paymentMix(array $orders): array
    {
        return array_map(fn ($method) => [
            'label' => self::PAYMENT_NAMES[$method],
            'color' => self::PAYMENT_COLORS[$method],
            'value' => array_sum(array_column(array_filter($orders, fn ($o) => $o['method'] === $method), 'total')),
        ], ['GCASH', 'CASH', 'MAYA']);
    }

    /** Average orders per day for each opening hour (7 AM – 9 PM), over the last 7 days. */
    private function ordersByHour(array $week): array
    {
        $hours = [];
        foreach (range(7, 21) as $hour) {
            $n = count(array_filter($week, fn ($o) => (int) Carbon::parse($o['paid_at'])->format('G') === $hour)) / 7;
            $label = ($hour % 12 ?: 12);
            $hours[] = [
                'label' => $label.($hour < 12 ? 'a' : 'p'),
                'value' => round($n, 1),
                'tip' => "<b>{$label}:00 ".($hour < 12 ? 'AM' : 'PM').'</b><span>'.number_format($n, 1).' orders per day</span>',
            ];
        }

        return $hours;
    }

    /** @return array<int, array{name: string, qty: int, revenue: float}> top six by quantity */
    private function bestSellers(array $orders): array
    {
        $counts = [];
        foreach ($orders as $order) {
            foreach ($order['items'] as $line) {
                $c = &$counts[$line['menu_item']];
                $c ??= ['name' => $line['name'], 'qty' => 0, 'revenue' => 0];
                $c['qty'] += $line['qty'];
                $c['revenue'] += $line['unit_price'] * $line['qty'];
                unset($c);
            }
        }
        usort($counts, fn ($a, $b) => $b['qty'] <=> $a['qty']);

        return array_slice($counts, 0, 6);
    }

    /** The "Needs attention" list: each row links to where it's handled. */
    private function attention(): array
    {
        $orders = collect(DemoData::orders());
        $low = collect(DemoData::ingredients())->filter(fn ($i) => StockLevel::of($i) !== StockLevel::OK)->pluck('name');
        $soldOut = collect(DemoData::items())->filter(fn ($i) => DemoData::isSoldOut($i))->pluck('name');

        return [
            ['label' => 'Orders waiting for payment', 'count' => $orders->filter(fn ($o) => CashierActions::unpaid($o))->count(), 'bad' => 'chip-red', 'ok' => 'chip-green', 'url' => route('cashier.orders.index'), 'note' => null],
            ['label' => 'Orders in the kitchen', 'count' => $orders->whereIn('status', ['paid', 'preparing'])->count(), 'bad' => 'chip-gcash', 'ok' => 'chip-gcash', 'url' => route('kitchen.display'), 'note' => null],
            ['label' => 'Ingredients low or out', 'count' => $low->count(), 'bad' => 'chip-caramel', 'ok' => 'chip-green', 'url' => route('inventory.ingredients.index'), 'note' => $low->take(4)->join(', ').($low->count() > 4 ? '…' : '')],
            ['label' => 'Menu items sold out', 'count' => $soldOut->count(), 'bad' => 'chip-red', 'ok' => 'chip-green', 'url' => route('admin.menu-items.index'), 'note' => $soldOut->join(', ')],
        ];
    }
}
