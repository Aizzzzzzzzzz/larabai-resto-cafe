<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PosController extends Controller
{
    public function index(): View
    {
        return view('cashier.pos');
    }

    /** Charge the ticket: one form holding every line (built by JS as array inputs). */
    public function store(): RedirectResponse
    {
        return $this->demoSaved('cashier.payments.create', ['order' => 'demo']);
    }
}
