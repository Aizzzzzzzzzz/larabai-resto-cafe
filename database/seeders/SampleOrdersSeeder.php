<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Discount;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusLog;
use App\Models\Payment;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\Catalog;
use App\Support\AuditArea;
use Database\Seeders\Data\SampleAudit;
use Database\Seeders\Data\SampleData;
use Database\Seeders\Data\SampleMovements;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Today's 13 sample orders (one or more in every stage), with their stock history and the audit log.
 * The rows are written exactly as the sample data describes them: stock already reflects these orders, so
 * nothing is deducted again. Needs the users, tables, ingredients and menu first.
 */
class SampleOrdersSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::pluck('id', 'full_name');
        $tables = DB::table('dining_tables')->pluck('id', 'table_no');
        $items = DB::table('menu_items')->pluck('id', 'code');
        $ingredients = DB::table('ingredients')->pluck('id', 'code');

        // item code → "group:choice" → option_choices.id
        $choiceIds = [];
        foreach (DB::table('option_choices as c')
            ->join('option_groups as g', 'g.id', '=', 'c.option_group_id')
            ->join('menu_items as m', 'm.id', '=', 'g.menu_item_id')
            ->get(['c.id', 'c.code as choice', 'g.code as grp', 'm.code as item']) as $row) {
            $choiceIds[$row->item][$row->grp.':'.$row->choice] = $row->id;
        }

        $sequences = [];

        foreach (SampleData::orders() as $o) {
            $deducted = ($o['payment'] !== null || $o['is_cod']) && $o['status'] !== 'voided';

            $order = Order::create([
                'id' => $o['id'],
                'order_no' => $o['order_no'],
                'public_token' => $o['public_token'],
                'source' => $o['source'],
                'order_type' => $o['order_type'],
                'dining_table_id' => $o['table_no'] ? $tables[$o['table_no']] : null,
                'delivery_address' => $o['delivery_address'],
                'delivery_landmark' => $o['delivery_landmark'],
                'subtotal' => $o['subtotal'],
                'discount_amount' => $o['discount_amount'],
                'delivery_fee' => $o['delivery_fee'],
                'total' => $o['total'],
                'payment_method' => $o['payment_method'],
                'status' => $o['status'],
                'cancel_reason' => $o['cancel_reason'],
                'stock_deducted' => $deducted,
                'created_by' => $users[$o['created_by']] ?? null,
                'paid_at' => $o['paid_at'],
                'created_at' => $o['created_at'],
                'updated_at' => $o['created_at'],
            ]);

            foreach ($o['items'] as $line) {
                $item = OrderItem::create([
                    'order_id' => $order->id,
                    'menu_item_id' => $items[$line['menu_item']],
                    'item_name' => $line['name'],
                    'unit_price' => $line['unit_price'],
                    'qty' => $line['qty'],
                    'notes' => $line['notes'],
                ]);
                foreach ($line['options'] as $option) {
                    DB::table('order_item_options')->insert([
                        'order_item_id' => $item->id,
                        'option_choice_id' => $choiceIds[$line['menu_item']][$option['group'].':'.$option['choice']],
                        'group_code' => $option['group'],
                        'choice_code' => $option['choice'],
                        'choice_name' => $option['name'],
                        'extra_price' => $option['price'],
                    ]);
                }
            }

            foreach ($o['status_log'] as $log) {
                OrderStatusLog::create(['order_id' => $order->id, 'status' => $log['status'], 'changed_by' => $users[$log['by']] ?? null, 'changed_at' => $log['at']]);
            }

            if ($p = $o['payment']) {
                Payment::create([
                    'order_id' => $order->id,
                    'method' => $p['method'],
                    'amount' => $p['amount'],
                    'reference_no' => $p['reference_no'],
                    'status' => $p['status'],
                    'amount_tendered' => $p['amount_tendered'],
                    'change_due' => $p['change_due'],
                    'received_by' => $users[$p['received_by']] ?? null,
                    'paid_at' => $p['paid_at'] ?? $o['created_at'],
                    'voided_at' => $p['voided_at'],
                    'void_reason' => $p['void_reason'],
                ]);

                $prefix = substr($p['reference_no'], 0, 2);
                $sequences[$prefix] = max($sequences[$prefix] ?? 0, (int) substr($p['reference_no'], -4));
            }

            if ($d = $o['discount']) {
                Discount::create(['order_id' => $order->id] + $d);
            }

            $sequences['BB'] = max($sequences['BB'] ?? 0, $o['id']);
        }

        // The numbers the next order and payment continue from
        foreach ($sequences as $prefix => $last) {
            DB::table('daily_sequences')->insert(['prefix' => $prefix, 'seq_date' => now()->toDateString(), 'last_no' => $last]);
        }

        $orderIds = Order::pluck('id', 'order_no');
        $movements = [];
        foreach (SampleMovements::all() as $code => $moves) {
            foreach ($moves as $m) {
                $movements[] = [
                    'ingredient_id' => $ingredients[$code],
                    'type' => $m['type']->value,
                    'qty' => $m['qty'],
                    'qty_before' => $m['qty_before'],
                    'qty_after' => $m['qty_after'],
                    'cost' => $m['cost'],
                    'order_id' => $m['reference'] ? ($orderIds[$m['reference']] ?? null) : null,
                    'note' => $m['note'] ?: null,
                    'user_id' => $users[$m['user']] ?? null,
                    'moved_at' => $m['moved_at'],
                ];
            }
        }
        foreach (array_chunk($movements, 200) as $chunk) {
            StockMovement::insert($chunk);
        }

        $audit = array_map(fn ($a) => [
            'user_id' => $users[$a['user']] ?? null,
            'user_name' => $a['user'],
            'role' => $a['role'],
            'module' => AuditArea::of($a['action'])[0],
            'action' => $a['action'],
            'detail' => $a['detail'],
            'logged_at' => $a['logged_at'],
        ], SampleAudit::all());
        AuditLog::insert($audit);

        Catalog::flush();
    }
}
