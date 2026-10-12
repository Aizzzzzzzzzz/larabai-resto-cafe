<?php

namespace App\View\Components;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Support\Format;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Customer order progress (mockup customer/track timeline):
 * Order placed → Payment confirmed → Preparing → Ready / Out for delivery → Served / Picked up / Delivered.
 * Cash on delivery shows "Pay the rider" until the cash is collected. Cancelled and voided
 * orders show a short two-step timeline instead.
 */
class Tracker extends Component
{
    /** @var array<int, array{class: string, label: string, sub: string, time: string}> */
    public array $steps;

    public function __construct(public mixed $order)
    {
        $this->steps = $this->buildSteps();
    }

    private function buildSteps(): array
    {
        $o = $this->order;
        $status = OrderStatus::from($o['status']);

        if (in_array($status, [OrderStatus::Cancelled, OrderStatus::Voided], true)) {
            return [
                ['class' => 'done', 'label' => 'Order placed', 'sub' => '', 'time' => Format::time($o['created_at'])],
                ['class' => 'now', 'label' => $status->label(), 'sub' => (string) $o['cancel_reason'], 'time' => Format::time($this->timeOf($status->value))],
            ];
        }

        $method = PaymentMethod::from($o['payment_method']);
        $ref = $o['payment']['reference_no'] ?? null;
        $isCod = (bool) $o['is_cod'];

        $steps = [
            ['key' => 'placed', 'label' => 'Order placed', 'sub' => Format::orderType($o['order_type'], $o['table_no'])],
            $isCod && ! $o['paid_at']
                ? ['key' => 'paid', 'label' => 'Pay the rider', 'sub' => 'Cash on delivery']
                : ['key' => 'paid', 'label' => 'Payment confirmed', 'sub' => $method->label().($ref ? ' · '.$ref : '')],
            ['key' => 'preparing', 'label' => 'Preparing', 'sub' => 'In the kitchen'],
            ['key' => 'ready', 'label' => $o['order_type'] === 'delivery' ? 'Out for delivery' : 'Ready', 'sub' => $o['order_type'] === 'take-out' ? 'Pick up at the counter' : ''],
            ['key' => 'completed', 'label' => match ($o['order_type']) {
                'dine-in' => 'Served to your table',
                'delivery' => 'Delivered',
                default => 'Picked up',
            }, 'sub' => ''],
        ];

        $rank = $status->rank();

        return array_map(function ($step, $i) use ($rank, $status, $isCod, $o) {
            $class = $i < $rank || ($i === $rank && $status === OrderStatus::Completed) ? 'done' : ($i === $rank ? 'now' : 'todo');
            if ($i === 1 && $isCod) {
                $class = $o['paid_at'] ? 'done' : 'todo'; // COD: the kitchen starts before the cash is collected
            }

            return [
                'class' => $class,
                'label' => $step['label'],
                'sub' => $i <= $rank ? $step['sub'] : '',
                'time' => $i <= $rank ? Format::time($this->timeOf($step['key'])) : '',
            ];
        }, $steps, array_keys($steps));
    }

    /** When the order reached a stage: placed = created_at, paid = paid_at, otherwise the latest status-log entry. */
    private function timeOf(string $key): ?string
    {
        return match ($key) {
            'placed' => $this->order['created_at'],
            'paid' => $this->order['paid_at'],
            default => collect($this->order['status_log'])->last(fn ($entry) => $entry['status'] === $key)['at'] ?? null,
        };
    }

    public function render(): View
    {
        return view('components.tracker');
    }
}
