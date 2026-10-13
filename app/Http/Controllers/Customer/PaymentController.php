<?php

namespace App\Http\Controllers\Customer;

use App\Exceptions\IllegalTransition;
use App\Exceptions\InsufficientStock;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\CustomerCart;
use App\Services\OrderWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Simulated GCash / Maya wallet screen. No real money moves and no provider is contacted. */
class PaymentController extends Controller
{
    public function create(string $order): View|RedirectResponse
    {
        $o = OrderController::mine($order);

        // Cash orders, and orders already paid or cancelled, have nothing to pay here
        if ($o['payment_method'] === 'CASH' || $o['status'] !== 'awaiting_payment') {
            return redirect()->route('customer.orders.success', $order);
        }

        return view('customer.pay', ['order' => $o]);
    }

    public function store(CustomerCart $cart, OrderWorkflow $workflow, string $order): RedirectResponse
    {
        $o = OrderController::mine($order);

        try {
            $workflow->payWithWallet(Order::findOrFail($o['id']));
        } catch (InsufficientStock $e) {
            return redirect()->route('customer.pay.create', $order)->with('error', $e->getMessage().' Nothing was charged — please see the counter.');
        } catch (IllegalTransition) {
            // Paid already (a double tap) or cancelled: show where the order stands instead of charging twice
            return redirect()->route('customer.orders.success', $order);
        }

        $cart->clear();

        return redirect()->route('customer.orders.success', $order);
    }
}
