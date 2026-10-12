<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CartController extends Controller
{
    public function index(): View
    {
        return view('customer.cart');
    }

    public function store(): RedirectResponse
    {
        return $this->demoSaved();
    }

    public function update(string $line): RedirectResponse
    {
        return $this->demoSaved('customer.cart.index');
    }

    public function destroy(string $line): RedirectResponse
    {
        return $this->demoSaved('customer.cart.index');
    }
}
