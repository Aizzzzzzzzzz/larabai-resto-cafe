<?php

namespace App\Http\Controllers\Cashier;

use App\Enums\DiscountType;
use App\Enums\PaymentMethod;
use App\Exceptions\IllegalTransition;
use App\Exceptions\InsufficientStock;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cashier\TakePaymentRequest;
use App\Models\Order;
use App\Services\Orders;
use App\Services\OrderWorkflow;
use App\Services\Pricing;
use App\Support\CashierActions;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Take payment: cash / GCash / Maya, collect cash on delivery, Senior/PWD discount. */
class PaymentController extends Controller
{
    public function create(string $order): View
    {
        $o = Orders::find($order) ?? abort(404);

        return view('cashier.payment', [
            'order' => $o,
            'discountTypes' => DiscountType::cases(),
            'quickCash' => Pricing::quickCash($o['total']),
        ]);
    }

    /**
     * Apply the discount, record the payment (PAID + reference no., cash tendered and change), mark the order
     * paid, deduct stock and write the audit log — all in one go, or not at all.
     */
    public function store(TakePaymentRequest $request, OrderWorkflow $workflow, string $order): RedirectResponse
    {
        $o = Orders::find($order) ?? abort(404);
        abort_unless(CashierActions::canTakePayment($o), 403);

        $discount = $request->boolean('discount') ? [
            'type' => DiscountType::from($request->input('discount_type')),
            'id_no' => trim($request->input('id_no')),
            'holder' => trim($request->input('holder_name')),
            'eligible' => (int) $request->input('eligible_count'),
            'diners' => (int) $request->input('diner_count'),
        ] : null;

        try {
            $workflow->takePayment(
                Order::findOrFail($o['id']),
                $request->user(),
                PaymentMethod::from($request->input('method')),
                $discount,
                $request->input('method') === 'CASH' ? (float) $request->input('amount_tendered') : null,
            );
        } catch (InsufficientStock $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (IllegalTransition) {
            abort(403);
        }

        return redirect()->route('cashier.receipt', $o['id'])->with('status', "Payment recorded for {$o['order_no']}.");
    }
}
