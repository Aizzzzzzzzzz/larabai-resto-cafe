<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Starts a customer ordering session from a scanned QR code (signed URLs). */
class SessionController extends Controller
{
    public function table(Request $request, int $table): RedirectResponse
    {
        // UI scaffold: tables 1–5 (document.md §1). Phase 3 checks dining_tables.
        abort_unless($table >= 1 && $table <= 5, 404);

        $request->session()->put('qr', ['type' => 'dine-in', 'table' => $table]);

        return redirect()->route('customer.menu');
    }

    public function counter(Request $request): RedirectResponse
    {
        $request->session()->put('qr', ['type' => 'counter', 'table' => null]);

        return redirect()->route('customer.menu');
    }
}
