<?php

namespace App\Support;

/**
 * Kitchen display rules: which orders are on the board, which column, and which ticket buttons
 * apply. The same rules guard the controller and OrderWorkflow.
 */
class KitchenActions
{
    /** Ticket buttons → the status they lead to. */
    public const MOVES = ['start', 'back', 'ready', 'done', 'recall'];

    /** Paid orders, plus cash-on-delivery orders (they pay the rider on arrival). */
    public static function onBoard(array|\ArrayAccess $o): bool
    {
        return in_array($o['status'], ['paid', 'preparing', 'ready'], true)
            || ($o['status'] === 'awaiting_payment' && $o['is_cod']);
    }

    /** new | prep | ready */
    public static function column(array|\ArrayAccess $o): string
    {
        return match ($o['status']) {
            'preparing' => 'prep',
            'ready' => 'ready',
            default => 'new',
        };
    }

    /** The timer starts when the order reaches the kitchen: payment, or placing a COD order. */
    public static function startedAt(array|\ArrayAccess $o): string
    {
        return $o['paid_at'] && ! $o['is_cod'] ? $o['paid_at'] : $o['created_at'];
    }

    public static function can(array|\ArrayAccess $o, string $move): bool
    {
        return match ($move) {
            'start' => self::onBoard($o) && self::column($o) === 'new',
            'back' => $o['status'] === 'preparing',
            'ready' => $o['status'] === 'preparing',
            // Unpaid cash on delivery stays open until the cashier records the rider's cash
            'done' => $o['status'] === 'ready' && (bool) $o['paid_at'],
            // From Ready back to Preparing, or a finished order back to Ready
            'recall' => in_array($o['status'], ['ready', 'completed'], true),
            default => false,
        };
    }

    /** Status an allowed move leads to. */
    public static function target(array|\ArrayAccess $o, string $move): string
    {
        return match ($move) {
            'start' => 'preparing',
            'back' => $o['paid_at'] ? 'paid' : 'awaiting_payment',
            'ready' => 'ready',
            'done' => 'completed',
            'recall' => $o['status'] === 'completed' ? 'ready' : 'preparing',
        };
    }

    public static function doneLabel(array|\ArrayAccess $o): string
    {
        return match ($o['order_type']) {
            'dine-in' => 'Served',
            'delivery' => 'Handed to rider',
            default => 'Picked up',
        };
    }
}
