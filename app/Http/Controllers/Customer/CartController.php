<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\AddToCartRequest;
use App\Http\Requests\Customer\UpdateCartLineRequest;
use App\Services\CustomerCart;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** The cart lives in the session, so these work for real even in the UI scaffold. */
class CartController extends Controller
{
    public function index(CustomerCart $cart): View
    {
        if ($notes = $cart->sync()) {
            session()->now('status', implode(' ', $notes));
        }

        return view('customer.cart', [
            'lines' => $cart->lines(),
            'subtotal' => $cart->subtotal(),
        ]);
    }

    public function store(AddToCartRequest $request, CustomerCart $cart): RedirectResponse
    {
        $line = $cart->add(
            $request->input('menu_item'),
            $request->chosenOptions(),
            (int) $request->input('qty'),
            $request->input('notes'),
        );

        return redirect()->route('customer.menu')->with('status', "Added {$request->input('qty')} × {$line['name']}");
    }

    public function update(UpdateCartLineRequest $request, CustomerCart $cart, int $line): RedirectResponse
    {
        $old = $cart->setQty($line, (int) $request->input('qty')) ?? abort(404);

        return (int) $request->input('qty') === 0
            ? redirect()->route('customer.cart.index')->with('status', "Removed {$old['name']}")
            : redirect()->route('customer.cart.index');
    }

    public function destroy(CustomerCart $cart, int $line): RedirectResponse
    {
        $old = $cart->setQty($line, 0) ?? abort(404);

        return redirect()->route('customer.cart.index')->with('status', "Removed {$old['name']}");
    }
}
