<?php

namespace Database\Seeders\Data;

use App\Enums\MovementType;
use App\Services\StockUsage;
use Illuminate\Support\Carbon;

/**
 * Sample stock history (document.md §4.16) so the history screen has something to show.
 * Built from today's sample orders with the real usage rules: paid orders (and cash-on-delivery
 * orders, deducted when placed) use stock; the voided order gives it back. Each ingredient starts
 * from an opening count worked out so its running balance ends at its current stock.
 *
 * Seed data only — the running app writes these rows through StockService.
 */
class SampleMovements
{
    /** Newest first, at most $limit rows. */
    public static function for(string $ingredientCode, int $limit = 40): array
    {
        return array_slice(array_reverse(self::all()[$ingredientCode] ?? []), 0, $limit);
    }

    /** @return array<string, array<int, array>> ingredient code => movements, oldest first */
    public static function all(): array
    {
        $raw = [];
        $push = function (string $code, MovementType $type, float $qty, string $at, string $ref, string $note, string $user) use (&$raw) {
            $raw[$code][] = ['type' => $type, 'qty' => round($qty, 2), 'moved_at' => $at, 'reference' => $ref, 'note' => $note, 'user' => $user];
        };

        foreach (SampleData::orders() as $order) {
            $usage = StockUsage::forOrder($order['items']);
            $user = $order['created_by'] ?? 'System (auto)';
            $deducted = $order['payment'] !== null || $order['is_cod'];
            // Cash on delivery goes to the kitchen unpaid, so its stock is used when it's placed
            $usedAt = $order['paid_at'] ?? $order['created_at'];

            if ($deducted) {
                foreach ($usage as $code => $amount) {
                    $push($code, MovementType::Sale, -$amount, $usedAt, $order['order_no'], '', $user);
                }
            }
            if ($order['status'] === 'voided') {
                foreach ($usage as $code => $amount) {
                    $push($code, MovementType::Return, $amount, $order['payment']['voided_at'], $order['order_no'], 'Order voided', 'Jessa Manalo');
                }
            }
        }

        // A morning delivery and a physical count, by the stock clerk
        $first = collect($raw)->flatten(1)->min('moved_at') ?? now()->toDateTimeString();
        $morning = Carbon::parse($first)->subHours(2);
        $push('milk', MovementType::Purchase, 10000, $morning->copy()->addMinutes(20)->toDateTimeString(), '', 'Delivered by Central Luzon Dairy', 'Liza Garcia');
        $push('egg', MovementType::Adjust, -3, $morning->copy()->addMinutes(35)->toDateTimeString(), '', 'Physical count', 'Liza Garcia');

        $unitCost = array_column(SampleData::ingredients(), 'unit_cost', 'code');
        $stock = array_column(SampleData::ingredients(), 'stock_qty', 'code');
        $out = [];

        foreach ($raw as $code => $moves) {
            usort($moves, fn ($a, $b) => $a['moved_at'] <=> $b['moved_at']);

            // Opening count so that the last balance equals today's stock
            $opening = round($stock[$code] - array_sum(array_column($moves, 'qty')), 2);
            array_unshift($moves, ['type' => MovementType::Adjust, 'qty' => $opening, 'moved_at' => $morning->toDateTimeString(), 'reference' => '', 'note' => 'Opening stock', 'user' => 'Liza Garcia']);

            $balance = 0.0;
            foreach ($moves as $i => $move) {
                $moves[$i] += [
                    'ingredient' => $code,
                    'qty_before' => round($balance, 2),
                    'qty_after' => round($balance += $move['qty'], 2),
                    'cost' => round(abs($move['qty']) * $unitCost[$code], 2),
                ];
            }
            $out[$code] = $moves;
        }

        return $out;
    }
}
