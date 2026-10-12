<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function create(): View
    {
        return view('customer.checkout');
    }

    public function store(): RedirectResponse
    {
        // UI scaffold: pretend an order was placed and go to the simulated wallet screen.
        return $this->demoSaved('customer.pay.create', ['order' => 'demo']);
    }
}
