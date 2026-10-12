<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Support\CustomerOrderText;
use App\Support\DemoData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The customer's own orders, found by public token. Phase 3 also requires the token to be in the
 * visitor's session, so a guessed or shared link shows nothing (development-plan.md §3).
 */
class OrderController extends Controller
{
    /** "My orders": the newest order placed from this phone, or an empty state. */
    public function index(): View|RedirectResponse
    {
        $mine = MenuController::myOrders();

        return $mine
            ? redirect()->route('customer.orders.show', $mine[0]['public_token'])
            : view('customer.my-orders');
    }

    public function show(string $order): View
    {
        $o = DemoData::order($order) ?? abort(404);

        return view('customer.track', [
            'order' => $o,
            'mine' => MenuController::myOrders(),
            'hero' => CustomerOrderText::hero($o),
        ]);
    }

    /** Polled by the tracker every few seconds; the page reloads when the status changes. */
    public function status(string $order): JsonResponse
    {
        $o = DemoData::order($order) ?? abort(404);

        return response()->json(['status' => $o['status']]);
    }

    public function success(string $order): View
    {
        return view('customer.success', ['order' => DemoData::order($order) ?? abort(404)]);
    }

    public function cancel(string $order): RedirectResponse
    {
        $o = DemoData::order($order) ?? abort(404);
        abort_unless(CustomerOrderText::canCancel($o), 403);

        return $this->demoSaved('customer.orders.show', ['order' => $order]);
    }
}
