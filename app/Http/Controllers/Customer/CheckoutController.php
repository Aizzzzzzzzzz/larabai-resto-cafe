<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\CheckoutRequest;
use App\Services\CustomerCart;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    /**
     * UI scaffold: placing an order saves nothing — the customer is shown the sample order that
     * matches their choice. Phase 3 creates the order (status awaiting_payment) instead.
     */
    private const DEMO_ORDER = [
        'GCASH' => 'demo-0008',
        'MAYA' => 'demo-0010',
        'CASH' => ['dine-in' => 'demo-0012', 'take-out' => 'demo-0013', 'delivery' => 'demo-0011'],
    ];

    public function create(CustomerCart $cart): View|RedirectResponse
    {
        if ($cart->isEmpty()) {
            return redirect()->route('customer.cart.index');
        }

        return view('customer.checkout', [
            'notes' => $cart->sync(),
            'lines' => $cart->lines(),
            'subtotal' => $cart->subtotal(),
            'orderTypes' => CheckoutRequest::orderTypesFor(session('qr')),
            'deliveryFee' => (float) config('cafe.delivery.fee'),
        ]);
    }

    public function store(CheckoutRequest $request, CustomerCart $cart): RedirectResponse
    {
        $method = $request->input('payment_method');
        $token = $method === 'CASH' ? self::DEMO_ORDER['CASH'][$request->input('order_type')] : self::DEMO_ORDER[$method];

        $request->session()->push('my_orders', $token);

        // Cash: the order is placed now. E-wallets: the cart clears once the wallet screen confirms.
        if ($method === 'CASH') {
            $cart->clear();

            return $this->demoSaved('customer.orders.success', ['order' => $token]);
        }

        return $this->demoSaved('customer.pay.create', ['order' => $token]);
    }
}
