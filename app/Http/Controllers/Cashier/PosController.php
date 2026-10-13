<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cashier\ChargeRequest;
use App\Services\Catalog;
use App\Services\OrderService;
use App\Support\Format;
use App\Support\MenuOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PosController extends Controller
{
    public function index(): View
    {
        return view('cashier.pos', [
            'categories' => Catalog::categories(),
            'items' => Catalog::menu(),
            'tables' => array_values(array_filter(Catalog::tables(), fn ($t) => $t['is_active'])),
            // After a successful charge, the browser-kept ticket is emptied on the next visit
            'resetTicket' => (bool) session()->pull('pos_reset'),
        ]);
    }

    /**
     * Charge the ticket: one form holding every line (built by JS as array inputs). The order is created
     * unpaid; the cashier lands on its payment screen to take the money.
     */
    public function store(ChargeRequest $request, OrderService $orders): RedirectResponse
    {
        $lines = array_map(fn ($line) => [
            'menu_item' => $line['menu_item'],
            'qty' => (int) $line['qty'],
            'notes' => $line['notes'] ?? null,
            'options' => MenuOptions::flatten($line['options'] ?? []),
        ], $request->input('items'));

        $order = $orders->place($lines, [
            'source' => 'pos',
            'order_type' => $request->input('order_type'),
            'table_no' => $request->input('table_no') ? (int) $request->input('table_no') : null,
            'payment_method' => 'CASH', // the cashier picks the real method on the payment screen
        ], $request->user());

        $request->session()->put('pos_reset', true);

        return redirect()->route('cashier.payments.create', $order->id)
            ->with('status', "Order {$order->order_no} created · ".Format::peso($order->total));
    }
}
