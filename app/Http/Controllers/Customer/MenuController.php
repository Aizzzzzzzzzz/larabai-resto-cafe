<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\Catalog;
use App\Services\CustomerCart;
use App\Services\MyOrders;
use Illuminate\View\View;

class MenuController extends Controller
{
    public function index(CustomerCart $cart): View
    {
        if (! session('qr')) {
            return view('customer.gate');
        }

        if ($notes = $cart->sync()) {
            session()->now('status', implode(' ', $notes));
        }

        return view('customer.menu', [
            'categories' => Catalog::categories(),
            'items' => collect(Catalog::menu())->groupBy('category'),
            'cartCount' => $cart->count(),
            'cartSubtotal' => $cart->subtotal(),
            'myOrders' => MyOrders::all(),
        ]);
    }
}
