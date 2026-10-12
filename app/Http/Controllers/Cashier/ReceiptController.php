<?php

namespace App\Http\Controllers\Cashier;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class ReceiptController extends Controller
{
    public function show(string $order): View
    {
        return view('cashier.receipt', ['order' => $order]);
    }
}
