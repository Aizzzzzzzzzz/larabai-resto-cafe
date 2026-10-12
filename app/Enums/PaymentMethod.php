<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case GCash = 'GCASH';
    case Maya = 'MAYA';
    case Cash = 'CASH';

    public function label(): string
    {
        return match ($this) {
            self::GCash => 'GCash',
            self::Maya => 'Maya',
            self::Cash => 'Cash',
        };
    }

    /** Reference number prefix, e.g. GC-20261011-0001. */
    public function prefix(): string
    {
        return match ($this) {
            self::GCash => 'GC',
            self::Maya => 'MY',
            self::Cash => 'CS',
        };
    }
}
