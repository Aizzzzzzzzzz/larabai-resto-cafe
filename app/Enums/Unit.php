<?php

namespace App\Enums;

/** Unit of measure of an ingredient (document.md §4.6). Fixed once an ingredient is used in recipes. */
enum Unit: string
{
    case Grams = 'g';
    case Milliliters = 'ml';
    case Pieces = 'pc';

    public function label(): string
    {
        return match ($this) {
            self::Grams => 'grams (g)',
            self::Milliliters => 'milliliters (ml)',
            self::Pieces => 'pieces (pc)',
        };
    }
}
