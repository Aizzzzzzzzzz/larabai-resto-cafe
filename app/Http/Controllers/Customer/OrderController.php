<?php

namespace App\Http\Controllers\Customer;

use App\Exceptions\IllegalTransition;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\MyOrders;
use App\Services\Orders;
use App\Services\OrderWorkflow;
use App\Support\CustomerOrderText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The customer's own orders, found by public token. A link only works in the browser that placed the order
 * (its token is in that session); anywhere else it is a plain 404.
 */
class OrderController extends Controller
{
    /** "My orders": the newest order placed from this phone, or an empty state. */
    public function index(): View|RedirectResponse
    {
        $mine = MyOrders::all();

        return $mine
            ? redirect()->route('customer.orders.show', $mine[0]['public_token'])
            : view('customer.my-orders');
    }

    public function show(string $order): View
    {
        $o = self::mine($order);

        return view('customer.track', [
            'order' => $o,
            'mine' => MyOrders::all(),
            'hero' => CustomerOrderText::hero($o),
        ]);
    }

    /** Polled by the tracker every few seconds; the page reloads when the status changes. */
    public function status(string $order): JsonResponse
    {
        return response()->json(['status' => self::mine($order)['status']]);
    }

    public function success(string $order): View
    {
        return view('customer.success', ['order' => self::mine($order)]);
    }

    public function cancel(string $order, OrderWorkflow $workflow): RedirectResponse
    {
        $o = self::mine($order);
        abort_unless(CustomerOrderText::canCancel($o), 403);

        try {
            $workflow->cancel(Order::findOrFail($o['id']), 'Cancelled by customer', null);
        } catch (IllegalTransition) {
            abort(403); // the cashier got there first
        }

        return redirect()->route('customer.orders.show', $order)->with('status', 'Your order was cancelled. Nothing was charged.');
    }

    /** The order for a token in this phone's session, or a 404. */
    public static function mine(string $token): array
    {
        abort_unless(MyOrders::has($token), 404);

        return Orders::findByToken($token) ?? abort(404);
    }
}
