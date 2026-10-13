<?php

namespace App\Services;

/**
 * "My orders" on a customer's phone: the public tokens of orders placed in this browser session (customers
 * have no account). An order link only works for someone whose session holds its token, so a guessed or
 * forwarded link shows nothing.
 */
class MyOrders
{
    private const KEY = 'my_orders';

    public static function remember(string $token): void
    {
        session()->push(self::KEY, $token);
    }

    public static function has(string $token): bool
    {
        return in_array($token, session(self::KEY, []), true);
    }

    /** This phone's orders as arrays, newest first. */
    public static function all(): array
    {
        $tokens = array_reverse(array_unique(session(self::KEY, [])));
        if (! $tokens) {
            return [];
        }

        $byToken = Orders::query()->whereIn('public_token', $tokens)->get()->keyBy('public_token');

        return array_values(array_filter(array_map(
            fn ($token) => isset($byToken[$token]) ? Orders::toArray($byToken[$token]) : null,
            $tokens,
        )));
    }
}
