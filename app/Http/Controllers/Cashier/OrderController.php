<?php

namespace App\Http\Controllers\Cashier;

use App\Exceptions\IllegalTransition;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cashier\CancelOrderRequest;
use App\Models\Order;
use App\Services\Orders;
use App\Services\OrderWorkflow;
use App\Support\CashierActions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class OrderController extends Controller
{
    /** Tabs are ?tab= filters on one list (not separate screens). */
    public const TABS = [
        'awaiting' => 'Awaiting payment',
        'active' => 'In progress',
        'done' => 'Completed',
        'cancelled' => 'Cancelled / voided',
        'all' => 'All',
    ];

    public function index(Request $request): View
    {
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'awaiting';
        $q = trim((string) $request->query('q'));

        $orders = collect(Orders::today());
        $inTab = fn (string $t) => $orders->filter(fn ($o) => self::inTab($o, $t));

        $rows = $inTab($tab)
            ->filter(fn ($o) => $q === '' || Str::contains(Str::lower($o['order_no']), Str::lower($q)))
            ->sortBy('created_at', descending: $tab !== 'awaiting') // oldest unpaid first
            ->values();

        $paid = $orders->pluck('payment')->filter(fn ($p) => $p && $p['status'] === 'PAID');

        return view('cashier.orders', [
            'tab' => $tab,
            'q' => $q,
            'rows' => $rows,
            'counts' => collect(self::TABS)->map(fn ($label, $t) => $inTab($t)->count()),
            'kpis' => [
                'awaiting' => $inTab('awaiting')->count(),
                'today' => $orders->count(),
                'sales' => $paid->sum('amount'),
                'cash' => $paid->where('method', 'CASH')->sum('amount'),
            ],
            'reasons' => CancelOrderRequest::REASONS,
        ]);
    }

    /** Mark served / picked up / delivered. */
    public function complete(Request $request, OrderWorkflow $workflow, string $order): RedirectResponse
    {
        $o = $this->find($order);
        abort_unless(CashierActions::canComplete($o), 403);

        $this->attempt(fn () => $workflow->complete(Order::findOrFail($o['id']), $request->user()));

        return redirect()->route('cashier.orders.index', ['tab' => 'active'])->with('status', "{$o['order_no']} marked ".strtolower(CashierActions::completeLabel($o)).'.');
    }

    public function cancel(CancelOrderRequest $request, OrderWorkflow $workflow, string $order): RedirectResponse
    {
        $o = $this->find($order);
        abort_unless(CashierActions::canCancel($o), 403);

        $this->attempt(fn () => $workflow->cancel(Order::findOrFail($o['id']), $request->input('reason'), $request->user()));

        return redirect()->route('cashier.orders.index')->with('status', "{$o['order_no']} cancelled.");
    }

    /** Void a paid order: the payment is marked voided and the ingredients go back to stock. */
    public function void(CancelOrderRequest $request, OrderWorkflow $workflow, string $order): RedirectResponse
    {
        $o = $this->find($order);
        abort_unless(CashierActions::canVoid($o), 403);

        $this->attempt(fn () => $workflow->void(Order::findOrFail($o['id']), $request->input('reason'), $request->user()));

        return redirect()->route('cashier.orders.index', ['tab' => 'active'])->with('status', "{$o['order_no']} voided. The ingredients went back to stock.");
    }

    private static function inTab(array $o, string $tab): bool
    {
        return match ($tab) {
            'awaiting' => CashierActions::unpaid($o),
            'active' => in_array($o['status'], ['paid', 'preparing', 'ready'], true),
            'done' => $o['status'] === 'completed',
            'cancelled' => in_array($o['status'], ['cancelled', 'voided'], true),
            default => true,
        };
    }

    private function find(string $order): array
    {
        return Orders::find($order) ?? abort(404);
    }

    /** Someone else may have moved the order a moment ago: that is a refusal (403), not a crash. */
    private function attempt(callable $action): void
    {
        try {
            $action();
        } catch (IllegalTransition) {
            abort(403);
        }
    }
}
