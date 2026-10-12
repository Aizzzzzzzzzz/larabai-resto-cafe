<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\CustomerCart;
use App\Support\DemoData;
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

        // UI scaffold: menu from DemoData. Phase 3 queries categories + active menu items.
        $items = array_map(fn ($i) => $i + ['sold_out' => DemoData::isSoldOut($i)], DemoData::items());

        return view('customer.menu', [
            'categories' => DemoData::categories(),
            'items' => collect($items)->groupBy('category'),
            'cartCount' => $cart->count(),
            'cartSubtotal' => $cart->subtotal(),
            'myOrders' => self::myOrders(),
        ]);
    }

    /** Orders placed from this phone (tokens kept in the session), newest first. */
    public static function myOrders(): array
    {
        return array_values(array_filter(array_map(
            fn ($token) => DemoData::order($token),
            array_reverse(array_unique(session('my_orders', [])))
        )));
    }
}
