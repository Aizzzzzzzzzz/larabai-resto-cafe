<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cashier\ChargeRequest;
use App\Support\DemoData;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PosController extends Controller
{
    /**
     * UI scaffold: charging saves nothing — the cashier is taken to the sample order that matches
     * the order type. Phase 4 creates the order (source pos, created_by = cashier) instead.
     */
    private const DEMO_ORDER = ['dine-in' => 12, 'take-out' => 13];

    public function index(): View
    {
        $items = array_map(fn ($i) => $i + ['sold_out' => DemoData::isSoldOut($i)], DemoData::items());

        return view('cashier.pos', [
            'categories' => DemoData::categories(),
            'items' => $items,
            'tables' => DemoData::tables(),
            // After a successful charge, the browser-kept ticket is emptied on the next visit
            'resetTicket' => (bool) session()->pull('pos_reset'),
        ]);
    }

    /** Charge the ticket: one form holding every line (built by JS as array inputs). */
    public function store(ChargeRequest $request): RedirectResponse
    {
        $request->session()->put('pos_reset', true);

        return $this->demoSaved('cashier.payments.create', ['order' => self::DEMO_ORDER[$request->input('order_type')]]);
    }
}
