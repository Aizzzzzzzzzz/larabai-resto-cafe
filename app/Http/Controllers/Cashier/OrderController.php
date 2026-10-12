<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cashier\CancelOrderRequest;
use App\Support\CashierActions;
use App\Support\DemoData;
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

        // UI scaffold: today's sample orders. Phase 4 queries today's orders.
        $orders = collect(DemoData::orders());
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
    public function complete(string $order): RedirectResponse
    {
        abort_unless(CashierActions::canComplete($this->find($order)), 403);

        return $this->demoSaved('cashier.orders.index', ['tab' => 'active']);
    }

    public function cancel(CancelOrderRequest $request, string $order): RedirectResponse
    {
        abort_unless(CashierActions::canCancel($this->find($order)), 403);

        return $this->demoSaved('cashier.orders.index');
    }

    /** Void a paid order: the payment is marked voided and the ingredients go back to stock (Phase 4). */
    public function void(CancelOrderRequest $request, string $order): RedirectResponse
    {
        abort_unless(CashierActions::canVoid($this->find($order)), 403);

        return $this->demoSaved('cashier.orders.index', ['tab' => 'active']);
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
        return DemoData::order($order) ?? abort(404);
    }
}
