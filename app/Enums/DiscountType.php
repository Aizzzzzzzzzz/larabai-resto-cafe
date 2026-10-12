<?php

namespace App\Enums;

/** Senior Citizen / PWD card presented for the discount (document.md §4.15). */
enum DiscountType: string
{
    case Senior = 'Senior';
    case Pwd = 'PWD';

    public function label(): string
    {
        return match ($this) {
            self::Senior => 'Senior citizen',
            self::Pwd => 'PWD',
        };
    }
}
