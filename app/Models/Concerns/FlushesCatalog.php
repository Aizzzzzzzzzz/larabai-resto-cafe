<?php

namespace App\Models\Concerns;

use App\Services\Catalog;

/** The menu, stock, tables and staff lists are read as one snapshot per request; saving one of them refreshes it. */
trait FlushesCatalog
{
    protected static function bootFlushesCatalog(): void
    {
        static::saved(fn () => Catalog::flush());
        static::deleted(fn () => Catalog::flush());
    }
}
