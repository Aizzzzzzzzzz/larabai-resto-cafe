<?php

namespace App\Enums;

/** Why an ingredient's stock changed (document.md §4.16). */
enum MovementType: string
{
    case Sale = 'SALE';
    case Return = 'RETURN';
    case Purchase = 'PURCHASE';
    case Adjust = 'ADJUST';

    public function label(): string
    {
        return match ($this) {
            self::Sale => 'Used in order',
            self::Return => 'Returned (cancel/void)',
            self::Purchase => 'Restocked',
            self::Adjust => 'Stock count',
        };
    }
}
