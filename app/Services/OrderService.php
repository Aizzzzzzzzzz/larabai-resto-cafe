<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusLog;
use App\Models\User;
use App\Support\Format;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates orders (state table row #1): from a customer's cart or the cashier's ticket. The browser only ever
 * sends item codes, option codes, notes and quantities; every name, price and total is read from the menu here.
 */
class OrderService
{
    public function __construct(private StockService $stock) {}

    /**
     * @param  array<int, array{menu_item: string, qty: int, notes?: ?string, options: array<int, array{group: string, choice: string}>}>  $lines
     * @param  array{source: string, order_type: string, table_no?: ?int, payment_method: string, delivery_address?: ?string, delivery_landmark?: ?string}  $data
     */
    public function place(array $lines, array $data, ?User $by = null): Order
    {
        return DB::transaction(function () use ($lines, $data, $by) {
            $table = ($data['table_no'] ?? null) ? DiningTable::where('table_no', $data['table_no'])->where('is_active', true)->firstOrFail() : null;

            $priced = array_map(fn ($line) => $this->price($line), $lines);
            $subtotal = round(array_sum(array_map(fn ($l) => $l['unit_price'] * $l['qty'], $priced)), 2);
            $fee = $data['order_type'] === 'delivery' ? (float) config('cafe.delivery.fee') : 0.0;

            $order = Order::create([
                'order_no' => Sequence::next('BB'),
                'public_token' => Str::random(40),
                'source' => $data['source'],
                'order_type' => $data['order_type'],
                'dining_table_id' => $data['order_type'] === 'dine-in' ? $table?->id : null,
                'delivery_address' => $data['order_type'] === 'delivery' ? ($data['delivery_address'] ?? null) : null,
                'delivery_landmark' => $data['order_type'] === 'delivery' ? ($data['delivery_landmark'] ?? null) : null,
                'subtotal' => $subtotal,
                'discount_amount' => 0,
                'delivery_fee' => $fee,
                'total' => Pricing::total($subtotal, 0, $fee),
                'payment_method' => $data['payment_method'],
                'status' => OrderStatus::AwaitingPayment,
                'stock_deducted' => false,
                'created_by' => $by?->id,
            ]);

            foreach ($priced as $line) {
                $item = OrderItem::create([
                    'order_id' => $order->id,
                    'menu_item_id' => $line['menu_item_id'],
                    'item_name' => $line['name'],
                    'unit_price' => $line['unit_price'],
                    'qty' => $line['qty'],
                    'notes' => $line['notes'],
                ]);
                foreach ($line['options'] as $option) {
                    DB::table('order_item_options')->insert(['order_item_id' => $item->id] + $option);
                }
            }

            OrderStatusLog::create(['order_id' => $order->id, 'status' => OrderStatus::AwaitingPayment, 'changed_by' => $by?->id, 'changed_at' => now()]);

            // Cash on delivery goes to the kitchen unpaid, so its ingredients come out of stock now
            if ($order->isCod()) {
                $this->stock->deduct($order, $by);
            }

            if ($by && $data['source'] === 'pos') {
                AuditLogger::record('Created counter order', "{$order->order_no} · ".Format::peso($order->total), $by);
            }

            return $order;
        });
    }

    /**
     * Prices one line from the current menu: item price plus the chosen options' extras, with the names copied
     * so the order keeps them if the menu changes later.
     */
    private function price(array $line): array
    {
        $item = MenuItem::with('optionGroups.choices')->where('code', $line['menu_item'])->firstOrFail();
        $chosen = collect($line['options'] ?? [])->map(fn ($o) => $o['group'].':'.$o['choice'])->all();

        $options = [];
        foreach ($item->optionGroups as $group) {
            foreach ($group->choices as $choice) {
                if (in_array($group->code.':'.$choice->code, $chosen, true)) {
                    $options[] = [
                        'option_choice_id' => $choice->id,
                        'group_code' => $group->code,
                        'choice_code' => $choice->code,
                        'choice_name' => $choice->name,
                        'extra_price' => $choice->extra_price,
                    ];
                }
            }
        }

        return [
            'menu_item_id' => $item->id,
            'name' => $item->name,
            'unit_price' => round($item->price + array_sum(array_column($options, 'extra_price')), 2),
            'qty' => (int) $line['qty'],
            'notes' => ($line['notes'] ?? null) ?: null,
            'options' => $options,
        ];
    }
}
