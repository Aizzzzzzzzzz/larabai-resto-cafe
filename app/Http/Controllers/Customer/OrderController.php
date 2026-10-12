<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The customer's own orders. {order} is the order's public token — Phase 3 also requires
 * the token to be in the visitor's session (development-plan.md §3).
 */
class OrderController extends Controller
{
    public function index(): View
    {
        return view('customer.my-orders');
    }

    public function show(string $order): View
    {
        return view('customer.track', ['order' => $order]);
    }

    public function status(string $order): JsonResponse
    {
        return response()->json(['status' => 'preparing']);
    }

    public function success(string $order): View
    {
        return view('customer.success', ['order' => $order]);
    }

    public function cancel(string $order): RedirectResponse
    {
        return $this->demoSaved('customer.orders.show', ['order' => $order]);
    }
}
