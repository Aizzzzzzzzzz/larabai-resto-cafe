<?php

namespace App\Exceptions;

use RuntimeException;

/** An order was asked to do something its current stage doesn't allow (development-plan.md §3 state table). */
class IllegalTransition extends RuntimeException
{
    public static function for(string $orderNo, string $status, string $wanted): self
    {
        return new self("{$orderNo} is {$status}; it can't be {$wanted}.");
    }
}
