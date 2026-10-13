<?php

namespace Database\Seeders\Data;

use App\Services\Pricing;
use Illuminate\Support\Carbon;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Sample sales history for the demo database (document.md §3.7). Ports the mockup's history.sample():
 * the 30 days before today are generated (seeded per day, so the numbers are stable) and written to the
 * orders / payments tables by SampleHistorySeeder, so the dashboard has something to report on.
 */
class SampleSales
{
    private static ?array $history = null;

    /** The 30 generated days before today. */
    public static function history(): array
    {
        if (self::$history !== null && self::$history['for'] === now()->toDateString()) {
            return self::$history['orders'];
        }

        $items = SampleData::items();
        $popular = ['latte' => 9, 'spanish' => 9, 'brownsugar' => 8, 'matcha' => 7, 'tapa' => 6, 'americano' => 6, 'macchiato' => 5, 'wintermelon' => 5, 'javachip' => 4, 'adobo' => 4, 'croissant' => 4, 'okinawa' => 4, 'mocha' => 3, 'seasalt' => 3, 'ube' => 3, 'tocino' => 3, 'fries' => 3, 'choco' => 2, 'strawberry' => 2, 'cookies' => 2, 'matchafrappe' => 2, 'longganisa' => 2, 'spam' => 2, 'ensaymada' => 2, 'pandesal' => 2, 'clubhouse' => 2, 'taro' => 2];
        $itemWeights = array_map(fn ($i) => [$i, $popular[$i['code']] ?? 1], $items);
        $hours = [[7, 4], [8, 7], [9, 6], [10, 5], [11, 7], [12, 12], [13, 10], [14, 6], [15, 9], [16, 10], [17, 8], [18, 7], [19, 6], [20, 5], [21, 3]];
        $deliveryFee = (float) config('cafe.delivery.fee');
        $out = [];

        for ($d = 30; $d >= 1; $d--) {
            $day = now()->startOfDay()->subDays($d);
            $engine = new Randomizer(new Mt19937((int) $day->format('Ymd')));
            $r = fn () => $engine->getInt(0, 999_999) / 1_000_000;
            $pick = function (array $weights) use ($r) {
                $x = $r() * array_sum(array_column($weights, 1));
                foreach ($weights as [$value, $weight]) {
                    if (($x -= $weight) <= 0) {
                        return $value;
                    }
                }

                return $weights[0][0];
            };

            $count = (int) round(($day->isWeekend() ? 172 : 138) * (0.85 + $r() * 0.3));
            for ($n = 0; $n < $count; $n++) {
                $at = $day->copy()->addHours($pick($hours))->addMinutes((int) floor($r() * 60));
                $lineCount = $r() < 0.55 ? 1 : ($r() < 0.8 ? 2 : 3);
                $lines = [];

                for ($k = 0; $k < $lineCount; $k++) {
                    $item = $pick($itemWeights);
                    $extra = 0;
                    foreach ($item['options'] as $group) {
                        if ($group['type'] === 'single') {
                            $choice = $group['code'] === 'size' ? $group['choices'][$r() < 0.42 ? 1 : 0] : $group['choices'][(int) floor($r() * count($group['choices']))];
                            $extra += $choice['price'];
                        } elseif ($r() < 0.28) {
                            $extra += $group['choices'][(int) floor($r() * count($group['choices']))]['price'];
                        }
                    }
                    $lines[] = ['menu_item' => $item['code'], 'name' => $item['name'], 'qty' => $r() < 0.1 ? 2 : 1, 'unit_price' => $item['price'] + $extra];
                }

                $subtotal = (float) array_sum(array_map(fn ($l) => $l['unit_price'] * $l['qty'], $lines));
                $type = $pick([['dine-in', 60], ['take-out', 32], ['delivery', 8]]);
                $method = $pick([['GCASH', 55], ['CASH', 35], ['MAYA', 10]]);
                $discount = $r() < 0.06 ? Pricing::seniorDiscount($subtotal, 1, max(1, $lineCount)) : 0.0;
                $fee = $type === 'delivery' ? $deliveryFee : 0.0;

                $out[] = [
                    'paid_at' => $at->toDateTimeString(),
                    'items' => $lines,
                    'subtotal' => round($subtotal, 2),
                    'discount' => $discount,
                    'total' => Pricing::total($subtotal, $discount, $fee),
                    'method' => $method,
                    'type' => $type,
                ];
            }
        }

        self::$history = ['for' => now()->toDateString(), 'orders' => $out];

        return $out;
    }
}
