<?php

namespace App\Enums;

enum OrderType: string
{
    case DineIn = 'dine-in';
    case TakeOut = 'take-out';
    case Delivery = 'delivery';

    public function label(): string
    {
        return match ($this) {
            self::DineIn => 'Dine-in',
            self::TakeOut => 'Take-out',
            self::Delivery => 'Delivery',
        };
    }
}
