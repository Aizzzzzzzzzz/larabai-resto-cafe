<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\CustomerCart;
use App\Support\DemoData;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Simulated GCash / Maya wallet screen. No real money moves and no provider is contacted. */
class PaymentController extends Controller
{
    public function create(string $order): View|RedirectResponse
    {
        $o = DemoData::order($order) ?? abort(404);

        if ($o['payment_method'] === 'CASH') {
            return redirect()->route('customer.orders.success', $order);
        }

        return view('customer.pay', ['order' => $o]);
    }

    public function store(CustomerCart $cart, string $order): RedirectResponse
    {
        DemoData::order($order) ?? abort(404);

        // Phase 3: record the payment (PAID + reference no.), mark the order paid, deduct stock.
        $cart->clear();

        return $this->demoSaved('customer.orders.success', ['order' => $order]);
    }
}
