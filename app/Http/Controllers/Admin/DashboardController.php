<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\Catalog;
use App\Services\Orders;
use App\Services\Reports;
use App\Support\CashierActions;
use App\Support\CurrentStaff;
use App\Support\Format;
use App\Support\StockLevel;
use Illuminate\View\View;

/**
 * Sales dashboard (document.md §3.7). Every figure counts paid orders that weren't voided,
 * by the time they were paid; see App\Services\Reports.
 */
class DashboardController extends Controller
{
    private const PAYMENT_COLORS = ['GCASH' => 'var(--pay-gcash)', 'CASH' => 'var(--pay-cash)', 'MAYA' => 'var(--pay-maya)'];

    private const PAYMENT_NAMES = ['GCASH' => 'GCash', 'CASH' => 'Cash', 'MAYA' => 'Maya'];

    public function index(): View
    {
        $day = fn (int $ago) => now()->startOfDay()->subDays($ago);

        $today = Reports::sales($day(0), $day(-1));
        // The same weekday last week, up to the same time of day, so the comparison is fair mid-shift
        $lastWeek = Reports::sales($day(7), now()->subDays(7));

        return view('admin.dashboard', [
            'greeting' => $this->greeting(),
            'kpis' => [
                'sales' => $today['total'], 'salesBefore' => $lastWeek['total'],
                'orders' => $today['count'], 'ordersBefore' => $lastWeek['count'],
                'average' => $today['count'] ? $today['total'] / $today['count'] : 0,
                'averageBefore' => $lastWeek['count'] ? $lastWeek['total'] / $lastWeek['count'] : 0,
                'discounts' => $today['discount'],
            ],
            'salesChart' => $this->salesByDay($day),
            'mixLast30' => $this->paymentMix(Reports::paymentMix($day(30), $day(-1))),
            'mixToday' => $today['count'] ? $this->paymentMix(Reports::paymentMix($day(0), $day(-1))) : null,
            'hoursChart' => $this->ordersByHour(Reports::ordersByHour($day(7), $day(0))),
            'best' => Reports::bestSellers($day(7), $day(-1)),
            'activity' => AuditLog::orderByDesc('logged_at')->orderByDesc('id')->limit(8)->get()->map(fn ($a) => [
                'logged_at' => $a->logged_at->toDateTimeString(),
                'user' => $a->user_name,
                'role' => $a->role,
                'action' => $a->action,
                'detail' => (string) $a->detail,
                'ago' => Format::ago($a->logged_at),
            ]),
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
    private function salesByDay(callable $day): array
    {
        $byDay = Reports::salesByDay($day(13), $day(-1));

        $data = [];
        for ($i = 13; $i >= 0; $i--) {
            $date = $day($i);
            $sales = $byDay[$date->toDateString()] ?? ['count' => 0, 'total' => 0.0];
            $data[] = [
                'label' => $date->format('M j'),
                'value' => $sales['total'],
                'tip' => '<b>'.$date->format('D, M j').'</b><span>'.peso($sales['total']).' · '.$sales['count'].' orders</span>',
            ];
        }

        return $data;
    }

    /** Share of sales by payment method, in the order GCash, Cash, Maya. */
    private function paymentMix(array $totals): array
    {
        return array_map(fn ($method) => [
            'label' => self::PAYMENT_NAMES[$method],
            'color' => self::PAYMENT_COLORS[$method],
            'value' => $totals[$method] ?? 0,
        ], ['GCASH', 'CASH', 'MAYA']);
    }

    /** Average orders per day for each opening hour (7 AM – 9 PM), over the last 7 days. */
    private function ordersByHour(array $counts): array
    {
        $hours = [];
        foreach (range(7, 21) as $hour) {
            $n = ($counts[$hour] ?? 0) / 7;
            $label = ($hour % 12 ?: 12);
            $hours[] = [
                'label' => $label.($hour < 12 ? 'a' : 'p'),
                'value' => round($n, 1),
                'tip' => "<b>{$label}:00 ".($hour < 12 ? 'AM' : 'PM').'</b><span>'.number_format($n, 1).' orders per day</span>',
            ];
        }

        return $hours;
    }

    /** The "Needs attention" list: each row links to where it's handled. */
    private function attention(): array
    {
        $orders = collect(Orders::today());
        $low = collect(Catalog::ingredients())->filter(fn ($i) => StockLevel::of($i) !== StockLevel::OK)->pluck('name');
        $soldOut = collect(Catalog::menu())->filter(fn ($i) => Catalog::isSoldOut($i))->pluck('name');

        return [
            ['label' => 'Orders waiting for payment', 'count' => $orders->filter(fn ($o) => CashierActions::unpaid($o))->count(), 'bad' => 'chip-red', 'ok' => 'chip-green', 'url' => route('cashier.orders.index'), 'note' => null],
            ['label' => 'Orders in the kitchen', 'count' => $orders->whereIn('status', ['paid', 'preparing'])->count(), 'bad' => 'chip-gcash', 'ok' => 'chip-gcash', 'url' => route('kitchen.display'), 'note' => null],
            ['label' => 'Ingredients low or out', 'count' => $low->count(), 'bad' => 'chip-caramel', 'ok' => 'chip-green', 'url' => route('inventory.ingredients.index'), 'note' => $low->take(4)->join(', ').($low->count() > 4 ? '…' : '')],
            ['label' => 'Menu items sold out', 'count' => $soldOut->count(), 'bad' => 'chip-red', 'ok' => 'chip-green', 'url' => route('admin.menu-items.index'), 'note' => $soldOut->join(', ')],
        ];
    }
}
