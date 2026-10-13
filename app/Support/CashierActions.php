<?php

namespace App\Support;

/**
 * What the cashier can do with an order at its current stage — the row buttons on the Orders
 * screen and the guard in each controller come from here, so they can't disagree.
 * OrderWorkflow checks the same rules before it moves an order (development-plan.md §3 state table).
 */
class CashierActions
{
    /** Not paid yet and still open (includes cash-on-delivery orders already in the kitchen). */
    public static function unpaid(array|\ArrayAccess $o): bool
    {
        return ! $o['paid_at'] && ! in_array($o['status'], ['cancelled', 'voided'], true);
    }

    public static function canTakePayment(array|\ArrayAccess $o): bool
    {
        return self::unpaid($o);
    }

    /** Cash on delivery, already in the kitchen: the rider brings the cash back. */
    public static function isCodCollection(array|\ArrayAccess $o): bool
    {
        return self::unpaid($o) && $o['status'] !== 'awaiting_payment';
    }

    public static function canCancel(array|\ArrayAccess $o): bool
    {
        return $o['status'] === 'awaiting_payment';
    }

    public static function canVoid(array|\ArrayAccess $o): bool
    {
        return in_array($o['status'], ['paid', 'preparing'], true) && (bool) $o['paid_at'];
    }

    /** Ready and paid. A cash-on-delivery order can't be marked delivered before the cash is collected. */
    public static function canComplete(array|\ArrayAccess $o): bool
    {
        return $o['status'] === 'ready' && (bool) $o['paid_at'];
    }

    public static function completeLabel(array|\ArrayAccess $o): string
    {
        return match ($o['order_type']) {
            'dine-in' => 'Mark served',
            'delivery' => 'Mark delivered',
            default => 'Mark picked up',
        };
    }

    public static function hasReceipt(array|\ArrayAccess $o): bool
    {
        return $o['status'] !== 'cancelled' && ! self::unpaid($o);
    }
}
