<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\CustomerCart;
use App\Support\DemoData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Starts a customer ordering session from a scanned QR code (signed URLs). The QR already says
 * where the customer is, so they never pick a table. Moving to another table or the counter
 * starts a fresh cart.
 */
class SessionController extends Controller
{
    public function table(Request $request, CustomerCart $cart, int $table): RedirectResponse
    {
        // UI scaffold: tables from DemoData. Phase 3 checks dining_tables.is_active.
        abort_unless(in_array($table, array_column(DemoData::tables(), 'table_no'), true), 404);

        return $this->start($request, $cart, ['type' => 'dine-in', 'table' => $table]);
    }

    public function counter(Request $request, CustomerCart $cart): RedirectResponse
    {
        return $this->start($request, $cart, ['type' => 'counter', 'table' => null]);
    }

    private function start(Request $request, CustomerCart $cart, array $qr): RedirectResponse
    {
        if ($request->session()->get('qr') !== $qr) {
            $cart->clear();
        }
        $request->session()->put('qr', $qr);

        return redirect()->route('customer.menu');
    }
}
