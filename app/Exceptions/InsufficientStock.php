<?php

namespace App\Exceptions;

use RuntimeException;

/** An order needs more of an ingredient than is on the shelf. The message names the item, safe to show. */
class InsufficientStock extends RuntimeException
{
    /** @param  string[]  $items  names of the menu items that can't be made */
    public static function forItems(array $items): self
    {
        $names = implode(', ', array_unique($items));

        return new self("Sorry, we don’t have enough stock to make {$names} right now.");
    }
}
