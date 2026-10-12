<?php

namespace App\Support;

use App\Enums\OrderType;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/** Display formatting shared by every screen — ports of the mockup's BB / BBC helpers. */
class Format
{
    /** ₱1,234.50 — or ₱1,235 without decimals. */
    public static function peso(int|float|string|null $amount, bool $decimals = true): string
    {
        return '₱'.number_format((float) $amount, $decimals ? 2 : 0);
    }

    /**
     * "Iced · Large (16 oz) · Oat milk, Extra espresso shot" — single choices first,
     * then add-ons / sides joined by commas. Sugar levels read "50% sugar".
     *
     * @param  iterable<array{group: string, name: string}>  $options
     */
    public static function optionText(iterable $options): string
    {
        $singles = [];
        $multis = [];

        foreach ($options as $option) {
            if (in_array($option['group'], ['addons', 'side'], true)) {
                $multis[] = $option['name'];
            } else {
                $singles[] = $option['group'] === 'sugar' ? $option['name'].' sugar' : $option['name'];
            }
        }

        return implode(' · ', array_filter([...$singles, implode(', ', $multis)]));
    }

    /** "Dine-in · Table 3", "Take-out" or "Delivery". */
    public static function orderType(string|OrderType $type, ?int $table = null): string
    {
        $type = $type instanceof OrderType ? $type : OrderType::from($type);

        return $type === OrderType::DineIn && $table ? "Dine-in · Table {$table}" : $type->label();
    }

    /** "Oct 11, 2026, 9:12 AM" */
    public static function dateTime(CarbonInterface|string|null $at): string
    {
        return $at ? Carbon::parse($at)->format('M j, Y, g:i A') : '';
    }

    /** "9:12 AM" */
    public static function time(CarbonInterface|string|null $at): string
    {
        return $at ? Carbon::parse($at)->format('g:i A') : '';
    }

    /** "just now", "8 min ago", "1 hr 5 min ago" */
    public static function ago(CarbonInterface|string $at): string
    {
        $minutes = (int) Carbon::parse($at)->diffInMinutes(now(), true);

        if ($minutes < 1) {
            return 'just now';
        }

        return $minutes < 60 ? "{$minutes} min ago" : intdiv($minutes, 60).' hr '.($minutes % 60).' min ago';
    }
}
