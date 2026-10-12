<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Take payment: cash / GCash / Maya, collect COD, Senior/PWD discount. */
class PaymentController extends Controller
{
    public function create(string $order): View
    {
        return view('cashier.payment', ['order' => $order]);
    }

    public function store(string $order): RedirectResponse
    {
        return $this->demoSaved('cashier.receipt', ['order' => $order]);
    }
}
