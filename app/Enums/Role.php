<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Cashier = 'cashier';
    case Kitchen = 'kitchen';
    case Inventory = 'inventory';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Owner / Admin',
            self::Cashier => 'Cashier',
            self::Kitchen => 'Kitchen',
            self::Inventory => 'Stock clerk',
        };
    }

    /** Screen a user of this role lands on after logging in (document.md §3.3). */
    public function homeRoute(): string
    {
        return match ($this) {
            self::Admin => 'admin.dashboard',
            self::Cashier => 'cashier.pos.index',
            self::Kitchen => 'kitchen.display',
            self::Inventory => 'inventory.ingredients.index',
        };
    }
}
