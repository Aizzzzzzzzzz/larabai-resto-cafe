<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\Catalog;
use App\Services\CustomerCart;
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
        abort_unless(collect(Catalog::tables())->contains(fn ($t) => $t['table_no'] === $table && $t['is_active']), 404);

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
