<?php

// Global template helpers (autoloaded via composer.json "files").

use App\Support\Format;

if (! function_exists('peso')) {
    /** ₱1,234.50 — {{ peso($order['total']) }} */
    function peso(int|float|string|null $amount, bool $decimals = true): string
    {
        return Format::peso($amount, $decimals);
    }
}
