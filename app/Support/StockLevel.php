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
}
