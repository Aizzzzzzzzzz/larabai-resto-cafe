<?php

namespace App\Support;

/** What the customer reads about their order — the mockup's heroText() and success messages. */
class CustomerOrderText
{
    /** Tracker headline and sentence for the order's current stage. */
    public static function hero(array|\ArrayAccess $o): array
    {
        $table = $o['order_type'] === 'dine-in' ? " to Table {$o['table_no']}" : '';

        return match ($o['status']) {
            'awaiting_payment' => $o['order_type'] === 'delivery'
                ? ['Order received', 'We’ll start preparing it shortly. Pay the rider in cash when it arrives.']
                : ['Waiting for payment', 'Pay '.Format::peso($o['total']).' at the counter and show your order number. We start preparing once it’s paid.'],
            'paid' => ['Order received', 'Your order is in the kitchen queue. A barista will pick it up soon.'],
            'preparing' => ['Preparing your order', 'Our team is making your order right now.'],
            'ready' => match ($o['order_type']) {
                'dine-in' => ['Ready!', "A server is bringing your order{$table}."],
                'delivery' => ['Out for delivery', 'Our rider is on the way to your address.'],
                default => ['Ready for pickup', 'Your order is waiting at the counter. Show your order number.'],
            },
            'completed' => ['Enjoy!', 'Thanks for ordering at Brew & Bite Café.'],
            'cancelled' => ['Order cancelled', 'This order was cancelled. Nothing was charged.'],
            'voided' => ['Order voided', 'The cashier voided this order. Please see the counter for your refund.'],
        };
    }

    /** "Payment: GCash · Paid" line on the tracker. */
    public static function paymentState(array|\ArrayAccess $o): string
    {
        $method = \App\Enums\PaymentMethod::from($o['payment_method'])->label();

        return $method.' · '.match (true) {
            $o['status'] === 'voided' => 'Voided',
            (bool) $o['paid_at'] => 'Paid',
            $o['status'] === 'cancelled' => 'Not charged',
            default => 'Unpaid',
        };
    }

    /** Customers may cancel only before paying (cash on delivery: until the kitchen starts). */
    public static function canCancel(array|\ArrayAccess $o): bool
    {
        return $o['status'] === 'awaiting_payment';
    }
}
