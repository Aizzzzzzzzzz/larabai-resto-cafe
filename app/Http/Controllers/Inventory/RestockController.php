<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\RestockRequest;
use App\Support\DemoData;
use Illuminate\Http\RedirectResponse;

/** Add a delivery (PURCHASE) or set the counted amount (ADJUST). */
class RestockController extends Controller
{
    public function store(RestockRequest $request, string $ingredient): RedirectResponse
    {
        $ing = DemoData::ingredient($ingredient) ?? abort(404);

        // A count that matches the books changes nothing, so it leaves no movement
        if ($request->input('mode') === 'count' && round((float) $request->input('qty'), 2) === round((float) $ing['stock_qty'], 2)) {
            return redirect()->route('inventory.ingredients.index')->with('status', 'Count matches. Nothing to change.');
        }

        // Phase 6: StockService records PURCHASE (+qty) or ADJUST (counted − on hand) with the note and the staff member.
        return $this->demoSaved('inventory.ingredients.index');
    }
}
