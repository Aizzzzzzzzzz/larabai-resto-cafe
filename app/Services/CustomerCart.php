<?php

namespace App\Services;

use App\Support\DemoData;
use App\Support\Format;
use Illuminate\Contracts\Session\Session;

/**
 * The customer's cart, kept in the Laravel session (one cart per phone, no login).
 *
 * The session stores only what the customer chose — item code, option codes, notes, quantity —
 * plus the unit price they last saw. Names and prices are always re-read from the current menu,
 * so a price change or a removed item is noticed (document.md §6 #9).
 *
 * UI scaffold: the menu comes from DemoData. Phase 3 reads the menu_items table instead.
 */
class CustomerCart
{
    private const KEY = 'cart';

    public const MAX_QTY = 20;

    public function __construct(private Session $session) {}

    /** Lines priced from the current menu, in the same shape as order lines (fits <x-order-lines>). */
    public function lines(): array
    {
        return array_values(array_filter(array_map(fn ($line) => $this->resolve($line), $this->stored())));
    }

    /**
     * Brings the saved cart in line with the current menu and says what changed,
     * e.g. ["Café Latte is now ₱150.00 each."].
     *
     * @return string[]
     */
    public function sync(): array
    {
        $notes = [];
        $kept = [];

        foreach ($this->stored() as $line) {
            $resolved = $this->resolve($line);

            if (! $resolved) {
                $notes[] = "{$line['name']} is no longer on the menu, so it was removed.";

                continue;
            }
            if ((float) $resolved['unit_price'] !== (float) $line['unit_price']) {
                $notes[] = "{$resolved['name']} is now ".Format::peso($resolved['unit_price']).' each.';
            }

            $kept[] = array_merge($line, [
                'name' => $resolved['name'],
                'unit_price' => $resolved['unit_price'],
                'choices' => $this->choicesOf($resolved),
            ]);
        }

        if ($notes) {
            $this->save($kept);
        }

        return $notes;
    }

    /**
     * Adds an item; the same item with the same options and note is merged into one line.
     *
     * @param  array<string, string|string[]>  $chosen  option group code => choice code(s)
     */
    public function add(string $itemCode, array $chosen, int $qty, ?string $notes): array
    {
        $line = $this->resolve([
            'id' => 0,
            'menu_item' => $itemCode,
            'choices' => $chosen,
            'notes' => $notes ?: null,
            'qty' => $qty,
            'name' => '',
            'unit_price' => 0,
        ]);

        $lines = $this->stored();
        $choices = $this->choicesOf($line);

        foreach ($lines as &$stored) {
            if ($stored['menu_item'] === $itemCode && $stored['choices'] == $choices && $stored['notes'] === $line['notes']) {
                $stored['qty'] = min(self::MAX_QTY, $stored['qty'] + $qty);
                $this->save($lines);

                return $line;
            }
        }
        unset($stored);

        $lines[] = [
            'id' => (collect($lines)->max('id') ?? 0) + 1,
            'menu_item' => $itemCode,
            'choices' => $choices,
            'notes' => $line['notes'],
            'qty' => $qty,
            'name' => $line['name'],
            'unit_price' => $line['unit_price'],
        ];
        $this->save($lines);

        return $line;
    }

    /** Sets a line's quantity; 0 removes it. Returns the line as it was, or null if there's no such line. */
    public function setQty(int $id, int $qty): ?array
    {
        $lines = $this->stored();
        $found = collect($lines)->firstWhere('id', $id);

        if (! $found) {
            return null;
        }

        $lines = $qty > 0
            ? array_map(fn ($l) => $l['id'] === $id ? array_merge($l, ['qty' => min(self::MAX_QTY, $qty)]) : $l, $lines)
            : array_values(array_filter($lines, fn ($l) => $l['id'] !== $id));
        $this->save($lines);

        return $found;
    }

    public function count(): int
    {
        return array_sum(array_column($this->lines(), 'qty'));
    }

    public function subtotal(): float
    {
        return round(array_sum(array_map(fn ($l) => $l['unit_price'] * $l['qty'], $this->lines())), 2);
    }

    public function isEmpty(): bool
    {
        return $this->stored() === [];
    }

    public function clear(): void
    {
        $this->session->forget(self::KEY);
    }

    /** Stored line + current menu → priced line, or null if the item left the menu. Unknown options are dropped. */
    private function resolve(array $line): ?array
    {
        $item = DemoData::item($line['menu_item']);

        if (! $item || ! $item['is_active']) {
            return null;
        }

        $options = [];
        foreach ($item['options'] as $group) {
            $picked = (array) ($line['choices'][$group['code']] ?? []);
            foreach ($group['choices'] as $choice) {
                if (in_array($choice['code'], $picked, true)) {
                    $options[] = ['group' => $group['code'], 'choice' => $choice['code'], 'name' => $choice['name'], 'price' => $choice['price']];
                }
            }
        }

        return [
            'id' => $line['id'],
            'menu_item' => $item['code'],
            'name' => $item['name'],
            'qty' => $line['qty'],
            'unit_price' => $item['price'] + array_sum(array_column($options, 'price')),
            'notes' => $line['notes'],
            'options' => $options,
            'sold_out' => DemoData::isSoldOut($item),
        ];
    }

    /** group code => [choice codes] of a resolved line, in menu order. */
    private function choicesOf(array $line): array
    {
        $choices = [];
        foreach ($line['options'] as $option) {
            $choices[$option['group']][] = $option['choice'];
        }

        return $choices;
    }

    private function stored(): array
    {
        return $this->session->get(self::KEY, []);
    }

    private function save(array $lines): void
    {
        $this->session->put(self::KEY, array_values($lines));
    }
}
