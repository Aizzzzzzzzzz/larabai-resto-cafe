<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Support\Num;
use Illuminate\Database\Eloquent\Builder;

/**
 * Orders as plain arrays for the screens (the shape the Blade views and shared components were built on),
 * read from the database. Writes never go through here: they use OrderWorkflow / OrderService.
 */
class Orders
{
    /** Stages an order can still move on from. */
    private const OPEN = ['awaiting_payment', 'paid', 'preparing', 'ready'];

    /** The orders on today's screens: placed today, plus anything still open from an earlier day. @return array<int, array> */
    public static function today(): array
    {
        return self::query()
            ->where(fn (Builder $q) => $q->where('created_at', '>=', now()->startOfDay())->orWhereIn('status', self::OPEN))
            ->orderBy('id')
            ->get()
            ->map(fn (Order $order) => self::toArray($order))
            ->all();
    }

    /** A staff screen finds an order by its number in the address. */
    public static function find(int|string $id): ?array
    {
        if (! ctype_digit((string) $id)) {
            return null;
        }

        $order = self::query()->find((int) $id);

        return $order ? self::toArray($order) : null;
    }

    /** A customer finds an order by its public token only — the order id never appears in their links. */
    public static function findByToken(string $token): ?array
    {
        $order = self::query()->where('public_token', $token)->first();

        return $order ? self::toArray($order) : null;
    }

    /** @return Builder<Order> */
    public static function query(): Builder
    {
        return Order::with(['items.menuItem', 'items.options', 'statusLogs.user', 'payment.receiver', 'discount', 'table', 'creator']);
    }

    /** Just what the stage rules (CashierActions, KitchenActions) look at, without loading the order's lines. */
    public static function state(Order $o): array
    {
        return [
            'status' => $o->status->value,
            'order_type' => $o->order_type->value,
            'is_cod' => $o->isCod(),
            'created_at' => $o->created_at->toDateTimeString(),
            'paid_at' => $o->paid_at?->toDateTimeString(),
        ];
    }

    public static function toArray(Order $o): array
    {
        $isCod = $o->isCod();

        return [
            'id' => $o->id,
            'order_no' => $o->order_no,
            'public_token' => $o->public_token,
            'source' => $o->source->value,
            'order_type' => $o->order_type->value,
            'table_no' => $o->table?->table_no,
            'delivery_address' => $o->delivery_address,
            'delivery_landmark' => $o->delivery_landmark,
            'subtotal' => $o->subtotal,
            'discount_amount' => $o->discount_amount,
            'delivery_fee' => $o->delivery_fee,
            'total' => $o->total,
            'payment_method' => $o->payment_method?->value,
            'status' => $o->status->value,
            'cancel_reason' => $o->cancel_reason,
            'is_cod' => $isCod,
            'created_by' => $o->creator?->full_name,
            'created_at' => $o->created_at->toDateTimeString(),
            'paid_at' => $o->paid_at?->toDateTimeString(),
            'items' => $o->items->map(fn ($line) => [
                'menu_item' => $line->menuItem->code,
                'name' => $line->item_name,
                'qty' => $line->qty,
                'unit_price' => Num::tidy($line->unit_price),
                'notes' => $line->notes,
                'options' => $line->options->map(fn ($option) => [
                    'group' => $option->group_code,
                    'choice' => $option->choice_code,
                    'name' => $option->choice_name,
                    'price' => Num::tidy($option->extra_price),
                ])->all(),
            ])->all(),
            'status_log' => $o->statusLogs->map(fn ($log) => [
                'status' => $log->status->value,
                'at' => $log->changed_at->toDateTimeString(),
                'by' => $log->user?->full_name,
            ])->all(),
            'payment' => $o->payment ? [
                'method' => $o->payment->method->value,
                'amount' => $o->payment->amount,
                'reference_no' => $o->payment->reference_no,
                'status' => $o->payment->status->value,
                'amount_tendered' => $o->payment->amount_tendered,
                'change_due' => $o->payment->change_due,
                'received_by' => $o->payment->receiver?->full_name,
                'paid_at' => $o->payment->paid_at?->toDateTimeString(),
                'voided_at' => $o->payment->voided_at?->toDateTimeString(),
                'void_reason' => $o->payment->void_reason,
            ] : null,
            'discount' => $o->discount ? [
                'discount_type' => $o->discount->discount_type->value,
                'id_no' => $o->discount->id_no,
                'holder_name' => $o->discount->holder_name,
                'eligible_count' => $o->discount->eligible_count,
                'diner_count' => $o->discount->diner_count,
                'amount' => $o->discount->amount,
            ] : null,
        ];
    }

    /** Whether an order can still move on (not completed, cancelled or voided). */
    public static function isOpen(OrderStatus $status): bool
    {
        return in_array($status->value, self::OPEN, true);
    }
}
