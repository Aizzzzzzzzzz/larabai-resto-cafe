<?php

namespace App\Services;

use App\Enums\DiscountType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\IllegalTransition;
use App\Models\Order;
use App\Models\OrderStatusLog;
use App\Models\Payment;
use App\Models\User;
use App\Support\CashierActions;
use App\Support\Format;
use App\Support\KitchenActions;
use Illuminate\Support\Facades\DB;

/**
 * The order state machine (development-plan.md §3 state table). It is the only code that changes
 * `orders.status`; each method checks the move is allowed from the order's current stage, does the move and
 * its side effects (payment, stock, status log, audit entry) in one transaction, or throws IllegalTransition.
 *
 * The order is re-read under a row lock first, so two people pressing buttons at once can't both win.
 */
class OrderWorkflow
{
    public function __construct(private StockService $stock, private PaymentService $payments) {}

    /**
     * #2 — the customer pays with GCash or Maya (simulated). Takes the stock out, records the payment,
     * and the order moves to Paid. A short ingredient refuses the payment and leaves the order unpaid.
     */
    public function payWithWallet(Order $order): Payment
    {
        return DB::transaction(function () use ($order) {
            $order = $this->lock($order);

            $this->require(
                $order->status === OrderStatus::AwaitingPayment && in_array($order->payment_method, [PaymentMethod::GCash, PaymentMethod::Maya], true),
                $order, 'paid online',
            );

            $this->stock->deduct($order);
            $payment = $this->payments->record($order, $order->payment_method, null);
            $order->forceFill(['paid_at' => $payment->paid_at])->save();
            $this->setStatus($order, OrderStatus::Paid, null);

            AuditLogger::customer('Payment recorded', $this->paymentDetail($order->order_no, $payment));

            return $payment;
        });
    }

    /**
     * #3 — the cashier takes the payment (cash, GCash or Maya), with an optional Senior/PWD discount, or collects
     * the cash of a delivery order that is already cooking. Cash on delivery stays at its kitchen stage.
     *
     * @param  array{type: DiscountType, id_no: string, holder: string, eligible: int, diners: int}|null  $discount
     */
    public function takePayment(Order $order, User $by, PaymentMethod $method, ?array $discount, ?float $tendered): Payment
    {
        return DB::transaction(function () use ($order, $by, $method, $discount, $tendered) {
            $order = $this->lock($order);
            $this->require(CashierActions::canTakePayment(Orders::state($order)), $order, 'paid');

            if ($discount) {
                $this->payments->applyDiscount($order, $discount['type'], $discount['id_no'], $discount['holder'], $discount['eligible'], $discount['diners']);
                AuditLogger::record('Applied discount', "{$order->order_no} · {$discount['type']->value} ".self::masked($discount['id_no']).' · −'.Format::peso($order->discount_amount), $by);
            }

            $waiting = $order->status === OrderStatus::AwaitingPayment;
            $this->stock->deduct($order, $by); // already done for a cash-on-delivery order, which does nothing

            $payment = $this->payments->record($order, $method, $by, $tendered);
            $order->forceFill(['paid_at' => $payment->paid_at, 'payment_method' => $method])->save();

            if ($waiting) {
                $this->setStatus($order, OrderStatus::Paid, $by);
            }

            AuditLogger::record('Payment recorded', $this->paymentDetail($order->order_no, $payment), $by);

            return $payment;
        });
    }

    /**
     * #4 — cancel an order that hasn't been paid (the customer, or the cashier). A cash-on-delivery order
     * has its stock out already, so it goes back.
     */
    public function cancel(Order $order, string $reason, ?User $by): void
    {
        DB::transaction(function () use ($order, $reason, $by) {
            $order = $this->lock($order);
            $this->require(CashierActions::canCancel(Orders::state($order)), $order, 'cancelled');

            $this->stock->giveBack($order, $by, 'Order cancelled');
            $order->forceFill(['cancel_reason' => $reason])->save();
            $this->setStatus($order, OrderStatus::Cancelled, $by);

            $detail = "{$order->order_no} · {$reason}";
            $by ? AuditLogger::record('Cancelled order', $detail, $by) : AuditLogger::customer('Cancelled order', $detail);
        });
    }

    /**
     * #5–#9 — a kitchen ticket button: start, back, ready, done or recall.
     */
    public function kitchenMove(Order $order, string $move, User $by): OrderStatus
    {
        return DB::transaction(function () use ($order, $move, $by) {
            $order = $this->lock($order);
            $view = Orders::state($order);
            $this->require(KitchenActions::can($view, $move), $order, "moved ({$move})");

            $to = OrderStatus::from(KitchenActions::target($view, $move));
            $this->setStatus($order, $to, $by);

            AuditLogger::record(match ($move) {
                'start' => 'Started preparing',
                'ready' => 'Marked ready',
                'done' => 'Completed order',
                'back' => 'Moved back to new orders',
                'recall' => 'Recalled order',
            }, $move === 'done' ? "{$order->order_no} · {$order->order_type->value}" : $order->order_no, $by);

            return $to;
        });
    }

    /** #8 — the cashier marks a ready order served / picked up / delivered. */
    public function complete(Order $order, User $by): void
    {
        DB::transaction(function () use ($order, $by) {
            $order = $this->lock($order);
            $this->require(CashierActions::canComplete(Orders::state($order)), $order, 'completed');

            $this->setStatus($order, OrderStatus::Completed, $by);
            AuditLogger::record('Completed order', "{$order->order_no} · {$order->order_type->value}", $by);
        });
    }

    /** #10 — void a paid order that hasn't reached Ready: the payment is voided and the stock goes back. */
    public function void(Order $order, string $reason, User $by): void
    {
        DB::transaction(function () use ($order, $reason, $by) {
            $order = $this->lock($order);
            $this->require(CashierActions::canVoid(Orders::state($order)), $order, 'voided');

            $this->payments->void($order->payment, $reason);
            $this->stock->giveBack($order, $by, 'Order voided');
            $order->forceFill(['cancel_reason' => $reason])->save();
            $this->setStatus($order, OrderStatus::Voided, $by);

            AuditLogger::record('Voided order', "{$order->order_no} · ".Format::peso($order->total)." · {$reason}", $by);
        });
    }

    /** Moves the order to a stage and writes the status log line. Only this method touches `orders.status`. */
    public function setStatus(Order $order, OrderStatus $to, ?User $by): void
    {
        $order->forceFill(['status' => $to])->save();

        OrderStatusLog::create([
            'order_id' => $order->id,
            'status' => $to,
            'changed_by' => $by?->id,
            'changed_at' => now(),
        ]);
    }

    private function lock(Order $order): Order
    {
        return Order::with('payment')->lockForUpdate()->findOrFail($order->id);
    }

    private function require(bool $allowed, Order $order, string $wanted): void
    {
        if (! $allowed) {
            throw IllegalTransition::for($order->order_no, $order->status->label(), $wanted);
        }
    }

    private function paymentDetail(string $orderNo, Payment $payment): string
    {
        return "{$orderNo} · {$payment->method->value} · ".Format::peso($payment->amount)." · {$payment->reference_no}";
    }

    /** Only the last four characters of a card number reach the audit log. */
    private static function masked(string $idNo): string
    {
        return '••••'.mb_substr($idNo, -4);
    }
}
