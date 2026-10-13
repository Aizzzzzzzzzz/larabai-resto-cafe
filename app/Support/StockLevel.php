<?php

namespace App\Support;

/** Stock status of an ingredient (mockup inventory.level): out at 0, low at or below the reorder level. */
class StockLevel
{
    public const OK = 'ok';

    public const LOW = 'low';

    public const OUT = 'out';

    public static function of(array|\ArrayAccess $ingredient): string
    {
        return match (true) {
            $ingredient['stock_qty'] <= 0 => self::OUT,
            $ingredient['stock_qty'] <= $ingredient['reorder_level'] => self::LOW,
            default => self::OK,
        };
    }

    public static function label(string $level): string
    {
        return ['ok' => 'In stock', 'low' => 'Low', 'out' => 'Out of stock'][$level];
    }

    /** CSS class of the status chip. */
    public static function chip(string $level): string
    {
        return ['ok' => 'chip-green', 'low' => 'chip-caramel', 'out' => 'chip-red'][$level];
    }

    /** Sort rank: out of stock first, then low, so alerts sit at the top of the list. */
    public static function rank(string $level): int
    {
        return ['out' => 0, 'low' => 1, 'ok' => 2][$level];
    }

    /** 12400 g → "12.4 kg", 850 ml → "850 ml", 12 pc → "12 pcs" (same output as resources/js/core/money.js). */
    public static function format(int|float $qty, string $unit): string
    {
        $n = round((float) $qty, 2);
        $text = fn (float $x) => rtrim(rtrim(number_format($x, 2), '0'), '.');

        return match (true) {
            $unit === 'g' && abs($n) >= 1000 => $text($n / 1000).' kg',
            $unit === 'ml' && abs($n) >= 1000 => $text($n / 1000).' L',
            $unit === 'pc' => $text($n).(abs($n) === 1.0 ? ' pc' : ' pcs'),
            default => $text($n).' '.$unit,
        };
    }

    /** "₱1.200/g" under ₱1, otherwise "₱3.50/g". */
    public static function costText(float $cost, string $unit): string
    {
        return '₱'.number_format($cost, $cost < 1 ? 3 : 2).'/'.$unit;
    }
}
