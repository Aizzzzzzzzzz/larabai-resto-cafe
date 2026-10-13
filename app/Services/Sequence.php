<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Daily running numbers: BB-20261014-0001 for orders, GC-/MY-/CS-20261014-0001 for payment references.
 * One counter per prefix per day, bumped under a row lock so two cashiers and a QR customer at the same
 * moment can never be handed the same number.
 */
class Sequence
{
    public static function next(string $prefix, ?CarbonInterface $at = null): string
    {
        $at ??= now();

        return DB::transaction(function () use ($prefix, $at) {
            $key = ['prefix' => $prefix, 'seq_date' => $at->toDateString()];

            DB::table('daily_sequences')->insertOrIgnore($key + ['last_no' => 0]);
            $row = DB::table('daily_sequences')->where($key)->lockForUpdate()->first();
            $number = $row->last_no + 1;
            DB::table('daily_sequences')->where($key)->update(['last_no' => $number]);

            return $prefix.'-'.$at->format('Ymd').'-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT);
        });
    }
}
