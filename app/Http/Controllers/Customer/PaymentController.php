<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Simulated GCash / Maya wallet screen. */
class PaymentController extends Controller
{
    public function create(string $order): View
    {
        return view('customer.pay', ['order' => $order]);
    }

    public function store(string $order): RedirectResponse
    {
        return $this->demoSaved('customer.orders.success', ['order' => $order]);
    }
}
