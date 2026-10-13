<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Exceptions\InsufficientStock;
use App\Models\Ingredient;
use App\Models\Order;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The only code that changes `ingredients.stock_qty`. Every change writes a `stock_movements` row in the
 * same transaction, with the amount before and after, so the history always adds up. Stock never goes
 * below zero: an order that can't be covered is refused with a message naming the item.
 */
class StockService
{
    /** Takes the order's ingredients out of stock (once — `stock_deducted` guards against doing it twice). */
    public function deduct(Order $order, ?User $by = null): void
    {
        DB::transaction(function () use ($order, $by) {
            if ($order->stock_deducted) {
                return;
            }

            $this->apply($order, -1, MovementType::Sale, $by, null);
            $order->forceFill(['stock_deducted' => true])->save();
        });
    }

    /** Puts the order's ingredients back after a cancel or void. Does nothing if none were taken. */
    public function giveBack(Order $order, ?User $by, string $note): void
    {
        DB::transaction(function () use ($order, $by, $note) {
            if (! $order->stock_deducted) {
                return;
            }

            $this->apply($order, 1, MovementType::Return, $by, $note);
            $order->forceFill(['stock_deducted' => false])->save();
        });
    }

    /** A delivery arrived: adds to stock (PURCHASE). */
    public function restock(Ingredient $ingredient, float $qty, ?string $note, User $by): StockMovement
    {
        return DB::transaction(fn () => $this->record($this->lock($ingredient), MovementType::Purchase, $qty, $note, $by));
    }

    /** A physical count: sets stock to what was counted (ADJUST). Returns null when the count matches the books. */
    public function count(Ingredient $ingredient, float $counted, ?string $note, User $by): ?StockMovement
    {
        return DB::transaction(function () use ($ingredient, $counted, $note, $by) {
            $locked = $this->lock($ingredient);
            $delta = round($counted - $locked->stock_qty, 2);

            return $delta == 0 ? null : $this->record($locked, MovementType::Adjust, $delta, $note, $by);
        });
    }

    /** Opening stock of a new ingredient. */
    public function open(Ingredient $ingredient, float $qty, User $by): ?StockMovement
    {
        return $qty > 0
            ? DB::transaction(fn () => $this->record($this->lock($ingredient), MovementType::Adjust, $qty, 'Opening stock', $by))
            : null;
    }

    /** @param  int  $sign  -1 takes the order's ingredients out, +1 puts them back */
    private function apply(Order $order, int $sign, MovementType $type, ?User $by, ?string $note): void
    {
        $lines = $this->lines($order);
        $usage = StockUsage::forOrder($lines);

        // Lock the rows in a fixed order so two orders at once can't deadlock each other
        $locked = Ingredient::whereIn('code', array_keys($usage))->orderBy('id')->lockForUpdate()->get()->keyBy('code');

        if ($sign < 0) {
            $short = array_keys(array_filter($usage, fn ($needed, $code) => $locked[$code]->stock_qty < $needed, ARRAY_FILTER_USE_BOTH));
            if ($short) {
                $items = array_column(array_filter($lines, fn ($line) => array_intersect(
                    array_keys(StockUsage::forLine($line['menu_item'], $line['qty'], $line['options'])),
                    $short,
                )), 'name');

                throw InsufficientStock::forItems($items);
            }
        }

        foreach ($usage as $code => $amount) {
            $this->record($locked[$code], $type, $sign * $amount, $note, $by, $order);
        }
    }

    private function record(Ingredient $ingredient, MovementType $type, float $delta, ?string $note, ?User $by, ?Order $order = null): StockMovement
    {
        $before = (float) $ingredient->stock_qty;
        $after = round($before + $delta, 2);

        $ingredient->forceFill(['stock_qty' => $after])->save();

        return StockMovement::create([
            'ingredient_id' => $ingredient->id,
            'type' => $type,
            'qty' => round($delta, 2),
            'qty_before' => $before,
            'qty_after' => $after,
            'cost' => round(abs($delta) * $ingredient->unit_cost, 2),
            'order_id' => $order?->id,
            'note' => $note ?: null,
            'user_id' => $by?->id,
            'moved_at' => now(),
        ]);
    }

    private function lock(Ingredient $ingredient): Ingredient
    {
        return Ingredient::lockForUpdate()->findOrFail($ingredient->id);
    }

    /** @return array<int, array{menu_item: string, name: string, qty: int, options: array}> */
    private function lines(Order $order): array
    {
        $order->loadMissing('items.menuItem', 'items.options');

        return $order->items->map(fn ($line) => [
            'menu_item' => $line->menuItem->code,
            'name' => $line->item_name,
            'qty' => $line->qty,
            'options' => $line->options->map(fn ($o) => ['group' => $o->group_code, 'choice' => $o->choice_code])->all(),
        ])->all();
    }
}
