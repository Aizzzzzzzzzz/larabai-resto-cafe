<?php

namespace App\Http\Controllers\Kitchen;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kitchen\MoveTicketRequest;
use App\Support\DemoData;
use App\Support\KitchenActions;
use Illuminate\Http\RedirectResponse;

/** Ticket buttons: Start, Back, Mark ready, Served / Picked up / Handed to rider, Recall. */
class OrderStatusController extends Controller
{
    public function update(MoveTicketRequest $request, string $order): RedirectResponse
    {
        $o = DemoData::order($order) ?? abort(404);
        abort_unless(KitchenActions::can($o, $request->input('move')), 403);

        // Phase 5: OrderWorkflow moves the order to KitchenActions::target() and writes the status log + audit.
        return $this->demoSaved('kitchen.display');
    }
}
