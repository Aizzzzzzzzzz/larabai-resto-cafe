<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Services\Pricing;
use App\Support\DemoData;
use Illuminate\View\View;

class ReceiptController extends Controller
{
    public function show(string $order): View
    {
        $o = DemoData::order($order) ?? abort(404);
        $d = $o['discount'];

        return view('cashier.receipt', [
            'order' => $o,
            'vat' => Pricing::vatBreakdown($o['subtotal'], $o['total'], $o['delivery_fee'], $d['eligible_count'] ?? null, $d['diner_count'] ?? null, $d['amount'] ?? 0),
        ]);
    }
}
