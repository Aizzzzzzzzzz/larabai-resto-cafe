<?php

namespace App\Support;

/** Numbers read from DECIMAL columns, tidied the way the screens (and the mockup's JavaScript) expect them. */
class Num
{
    /** 160.00 → 160, 0.095 → 0.095. Whole numbers become integers so they print and encode without ".0". */
    public static function tidy(int|float|string|null $value): int|float
    {
        $number = (float) $value;

        return floor($number) === $number && abs($number) < PHP_INT_MAX ? (int) $number : $number;
    }
}
