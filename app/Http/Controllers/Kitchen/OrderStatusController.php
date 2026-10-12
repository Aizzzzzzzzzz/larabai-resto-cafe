<?php

namespace App\Http\Controllers\Kitchen;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

/** Ticket buttons: Start, Back, Ready, Done, Recall (form field "to"). */
class OrderStatusController extends Controller
{
    public function update(string $order): RedirectResponse
    {
        return $this->demoSaved('kitchen.display');
    }
}
