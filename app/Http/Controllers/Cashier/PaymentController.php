<?php

namespace App\Http\Controllers\Cashier;

use App\Enums\DiscountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cashier\TakePaymentRequest;
use App\Services\Pricing;
use App\Support\CashierActions;
use App\Support\DemoData;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Take payment: cash / GCash / Maya, collect cash on delivery, Senior/PWD discount. */
class PaymentController extends Controller
{
    public function create(string $order): View
    {
        $o = DemoData::order($order) ?? abort(404);

        return view('cashier.payment', [
            'order' => $o,
            'discountTypes' => DiscountType::cases(),
            'quickCash' => Pricing::quickCash($o['total']),
        ]);
    }

    /**
     * Phase 4: apply the discount, record the payment (PAID + reference no., cash tendered and
     * change), mark the order paid, deduct stock, write the audit log.
     */
    public function store(TakePaymentRequest $request, string $order): RedirectResponse
    {
        $o = DemoData::order($order) ?? abort(404);
        abort_unless(CashierActions::canTakePayment($o), 403);

        return $this->demoSaved('cashier.receipt', ['order' => $order]);
    }
}
