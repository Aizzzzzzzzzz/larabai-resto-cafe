<?php

namespace Database\Seeders;

use App\Models\Discount;
use Database\Seeders\Data\SampleSales;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The 30 days before today as finished, paid orders, so the dashboard has weeks of sales to report on
 * (demo mode only). Lines carry the item and price; the options picked on those orders aren't kept.
 */
class SampleHistorySeeder extends Seeder
{
    private const PREFIX = ['GCASH' => 'GC', 'MAYA' => 'MY', 'CASH' => 'CS'];

    public function run(): void
    {
        $items = DB::table('menu_items')->pluck('id', 'code');
        $cashier = DB::table('users')->where('role', 'cashier')->orderBy('id')->value('id');
        $orderId = (int) DB::table('orders')->max('id');
        $orderNos = [];   // 'Ymd' => counter
        $references = []; // 'GC-Ymd' => counter

        $orders = $lines = $payments = $discounts = [];
        $flush = function () use (&$orders, &$lines, &$payments, &$discounts) {
            foreach (array_chunk($orders, 400) as $chunk) {
                DB::table('orders')->insert($chunk);
            }
            foreach (array_chunk($lines, 400) as $chunk) {
                DB::table('order_items')->insert($chunk);
            }
            foreach (array_chunk($payments, 400) as $chunk) {
                DB::table('payments')->insert($chunk);
            }
            foreach ($discounts as $discount) {
                Discount::create($discount);
            }
            $orders = $lines = $payments = $discounts = [];
        };

        foreach (SampleSales::history() as $sale) {
            $paidAt = Carbon::parse($sale['paid_at']);
            $placedAt = $paidAt->copy()->subMinutes(2)->toDateTimeString();
            $day = $paidAt->format('Ymd');
            $orderId++;

            $number = $orderNos[$day] = ($orderNos[$day] ?? 0) + 1;
            $prefix = self::PREFIX[$sale['method']];
            $reference = $references[$prefix.$day] = ($references[$prefix.$day] ?? 0) + 1;
            $counter = $sale['type'] === 'take-out';

            $orders[] = [
                'id' => $orderId,
                'order_no' => 'BB-'.$day.'-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT),
                'public_token' => Str::random(40),
                'source' => $counter ? 'pos' : 'qr',
                'order_type' => $sale['type'],
                'dining_table_id' => null,
                'delivery_address' => $sale['type'] === 'delivery' ? 'Sample address, Tarlac City' : null,
                'delivery_landmark' => null,
                'subtotal' => $sale['subtotal'],
                'discount_amount' => $sale['discount'],
                'delivery_fee' => round($sale['total'] - $sale['subtotal'] + $sale['discount'], 2),
                'total' => $sale['total'],
                'payment_method' => $sale['method'],
                'status' => 'completed',
                'cancel_reason' => null,
                'stock_deducted' => true,
                'created_by' => $counter ? $cashier : null,
                'paid_at' => $sale['paid_at'],
                'created_at' => $placedAt,
                'updated_at' => $sale['paid_at'],
            ];

            foreach ($sale['items'] as $line) {
                $lines[] = [
                    'order_id' => $orderId,
                    'menu_item_id' => $items[$line['menu_item']],
                    'item_name' => $line['name'],
                    'unit_price' => $line['unit_price'],
                    'qty' => $line['qty'],
                    'notes' => null,
                ];
            }

            $payments[] = [
                'order_id' => $orderId,
                'method' => $sale['method'],
                'amount' => $sale['total'],
                'reference_no' => $prefix.'-'.$day.'-'.str_pad((string) $reference, 4, '0', STR_PAD_LEFT),
                'status' => 'PAID',
                'amount_tendered' => null,
                'change_due' => null,
                'received_by' => $sale['method'] === 'CASH' ? $cashier : null,
                'paid_at' => $sale['paid_at'],
                'voided_at' => null,
                'void_reason' => null,
                'created_at' => $sale['paid_at'],
                'updated_at' => $sale['paid_at'],
            ];

            if ($sale['discount'] > 0) {
                $discounts[] = [
                    'order_id' => $orderId,
                    'discount_type' => 'Senior',
                    'id_no' => 'SC-'.$paidAt->format('Y').'-'.str_pad((string) (100000 + $orderId), 6, '0', STR_PAD_LEFT),
                    'holder_name' => 'Sample Senior',
                    'eligible_count' => 1,
                    'diner_count' => max(1, count($sale['items'])),
                    'amount' => $sale['discount'],
                ];
            }

            if (count($orders) >= 2000) {
                $flush();
            }
        }

        $flush();
    }
}
