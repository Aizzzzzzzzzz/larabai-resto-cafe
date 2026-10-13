<?php

namespace App\Http\Controllers\Customer;

use App\Exceptions\InsufficientStock;
use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\CheckoutRequest;
use App\Services\CustomerCart;
use App\Services\MyOrders;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CheckoutController extends Controller
{
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

    /**
     * Places the order (status "awaiting payment"). Cash: it is placed now and the customer pays at the counter
     * (or the rider). E-wallets: on to the simulated wallet screen, and the cart clears once that confirms.
     */
    public function store(CheckoutRequest $request, CustomerCart $cart, OrderService $orders): RedirectResponse
    {
        $qr = $request->session()->get('qr');
        $method = $request->input('payment_method');

        try {
            $order = $orders->place($cart->lines(), [
                'source' => 'qr',
                'order_type' => $request->input('order_type'),
                'table_no' => $qr['table'] ?? null,
                'payment_method' => $method,
                'delivery_address' => $request->input('delivery_address'),
                'delivery_landmark' => $request->input('delivery_landmark'),
            ]);
        } catch (InsufficientStock $e) {
            // Only cash on delivery takes stock out at this point
            return back()->withInput()->withErrors(['cart' => $e->getMessage()]);
        }

        MyOrders::remember($order->public_token);

        if ($method === 'CASH') {
            $cart->clear();

            return redirect()->route('customer.orders.success', $order->public_token);
        }

        return redirect()->route('customer.pay.create', $order->public_token);
    }
}
