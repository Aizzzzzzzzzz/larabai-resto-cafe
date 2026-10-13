<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Figures for the admin dashboard (document.md §3.7). Everything counts "paid, not voided" orders, by the time
 * they were paid. Sums and counts run in the database; only the hour-of-day split is grouped in PHP, because
 * MySQL and SQLite spell "hour of" differently.
 */
class Reports
{
    /** @return array{count: int, total: float, discount: float} */
    public static function sales(CarbonInterface $from, CarbonInterface $to): array
    {
        $row = self::paid($from, $to)->selectRaw('COUNT(*) as n, COALESCE(SUM(total), 0) as total, COALESCE(SUM(discount_amount), 0) as discount')->first();

        return ['count' => (int) $row->n, 'total' => (float) $row->total, 'discount' => (float) $row->discount];
    }

    /** Sales per calendar day: 'Y-m-d' => ['count' => n, 'total' => ₱]. Days with no sales are left out. */
    public static function salesByDay(CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = self::paid($from, $to)->selectRaw('DATE(paid_at) as day, COUNT(*) as n, SUM(total) as total')->groupBy('day')->get();

        return $rows->mapWithKeys(fn ($r) => [(string) $r->day => ['count' => (int) $r->n, 'total' => (float) $r->total]])->all();
    }

    /** Sales by payment method: 'GCASH' => ₱, … */
    public static function paymentMix(CarbonInterface $from, CarbonInterface $to): array
    {
        return self::paid($from, $to)->selectRaw('payment_method as method, SUM(total) as total')->groupBy('payment_method')->pluck('total', 'method')
            ->map(fn ($total) => (float) $total)->all();
    }

    /** How many orders were paid in each hour of the day: 7 => n, 8 => n… */
    public static function ordersByHour(CarbonInterface $from, CarbonInterface $to): array
    {
        $hours = [];
        foreach (self::paid($from, $to)->pluck('paid_at') as $paidAt) {
            $hour = (int) date('G', strtotime((string) $paidAt));
            $hours[$hour] = ($hours[$hour] ?? 0) + 1;
        }

        return $hours;
    }

    /** @return array<int, array{name: string, qty: int, revenue: float}> the items sold most, by quantity */
    public static function bestSellers(CarbonInterface $from, CarbonInterface $to, int $limit = 6): array
    {
        return DB::table('order_items as i')
            ->join('orders as o', 'o.id', '=', 'i.order_id')
            ->whereNotNull('o.paid_at')->where('o.status', '!=', 'voided')
            ->where('o.paid_at', '>=', $from)->where('o.paid_at', '<', $to)
            ->selectRaw('i.item_name as name, SUM(i.qty) as qty, SUM(i.unit_price * i.qty) as revenue')
            ->groupBy('i.menu_item_id', 'i.item_name')
            ->orderByDesc('qty')->orderBy('name')
            ->limit($limit)->get()
            ->map(fn ($r) => ['name' => $r->name, 'qty' => (int) $r->qty, 'revenue' => (float) $r->revenue])->all();
    }

    private static function paid(CarbonInterface $from, CarbonInterface $to)
    {
        return DB::table('orders')
            ->whereNotNull('paid_at')->where('status', '!=', 'voided')
            ->where('paid_at', '>=', $from)->where('paid_at', '<', $to);
    }
}
