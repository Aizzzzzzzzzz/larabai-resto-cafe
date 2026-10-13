<?php

namespace App\Services;

use App\Enums\DiscountType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Discount;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use InvalidArgumentException;

/**
 * Payment and discount rows. It doesn't decide whether an order may be paid or voided — OrderWorkflow does —
 * it only records the money: the reference number, the cash handed over and the change, the Senior/PWD discount.
 */
class PaymentService
{
    /** Applies the Senior/PWD discount to the order's total and records who the card belongs to. */
    public function applyDiscount(Order $order, DiscountType $type, string $idNo, string $holder, int $eligible, int $diners): Discount
    {
        $amount = Pricing::seniorDiscount($order->subtotal, $eligible, $diners);

        $order->forceFill([
            'discount_amount' => $amount,
            'total' => Pricing::total($order->subtotal, $amount, $order->delivery_fee),
        ])->save();

        return Discount::updateOrCreate(['order_id' => $order->id], [
            'discount_type' => $type,
            'id_no' => $idNo,
            'holder_name' => $holder,
            'eligible_count' => $eligible,
            'diner_count' => $diners,
            'amount' => $amount,
        ]);
    }

    /**
     * Records the payment for the order's current total. Cash needs the amount handed over, which must cover
     * the total; the change is worked out here, not taken from the browser.
     */
    public function record(Order $order, PaymentMethod $method, ?User $receivedBy, ?float $tendered = null): Payment
    {
        $total = $order->total;

        if ($method === PaymentMethod::Cash && ($tendered === null || round($tendered, 2) < $total)) {
            throw new InvalidArgumentException('The cash received is less than the amount due.');
        }

        return Payment::create([
            'order_id' => $order->id,
            'method' => $method,
            'amount' => $total,
            'reference_no' => Sequence::next($method->prefix()),
            'status' => PaymentStatus::Paid,
            'amount_tendered' => $method === PaymentMethod::Cash ? round($tendered, 2) : null,
            'change_due' => $method === PaymentMethod::Cash ? round($tendered - $total, 2) : null,
            'received_by' => $receivedBy?->id,
            'paid_at' => now(),
        ]);
    }

    public function void(Payment $payment, string $reason): void
    {
        $payment->forceFill([
            'status' => PaymentStatus::Voided,
            'voided_at' => now(),
            'void_reason' => $reason,
        ])->save();
    }
}
