<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class OrderController extends Controller
{
    /** Tabs (awaiting / in progress / completed / cancelled & voided) are ?tab= filters. */
    public function index(): View
    {
        return view('cashier.orders');
    }

    /** Mark served / picked up / delivered. */
    public function complete(string $order): RedirectResponse
    {
        return $this->demoSaved('cashier.orders.index');
    }

    public function cancel(string $order): RedirectResponse
    {
        return $this->demoSaved('cashier.orders.index');
    }

    public function void(string $order): RedirectResponse
    {
        return $this->demoSaved('cashier.orders.index');
    }
}
