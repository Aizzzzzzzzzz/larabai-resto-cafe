<?php

namespace App\Http\Controllers\Kitchen;

use App\Exceptions\IllegalTransition;
use App\Http\Controllers\Controller;
use App\Http\Requests\Kitchen\MoveTicketRequest;
use App\Models\Order;
use App\Services\Orders;
use App\Services\OrderWorkflow;
use App\Support\KitchenActions;
use Illuminate\Http\RedirectResponse;

/** Ticket buttons: Start, Back, Mark ready, Served / Picked up / Handed to rider, Recall. */
class OrderStatusController extends Controller
{
    public function update(MoveTicketRequest $request, OrderWorkflow $workflow, string $order): RedirectResponse
    {
        $o = Orders::find($order) ?? abort(404);
        abort_unless(KitchenActions::can($o, $request->input('move')), 403);

        try {
            $workflow->kitchenMove(Order::findOrFail($o['id']), $request->input('move'), $request->user());
        } catch (IllegalTransition) {
            abort(403); // another screen moved it first
        }

        return redirect()->route('kitchen.display');
    }
}
