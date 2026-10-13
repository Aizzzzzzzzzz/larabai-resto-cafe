<?php

namespace App\Http\Controllers\Kitchen;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kitchen\AvailabilityRequest;
use App\Models\MenuItem;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;

/** Sold-out switches: mark a menu item sold out, or available again. */
class AvailabilityController extends Controller
{
    /**
     * Sets the item's kitchen switch; the customer menu and the POS follow at once. Putting an item back
     * "available" clears the switch, so it follows stock again.
     */
    public function update(AvailabilityRequest $request, string $menuItem): RedirectResponse
    {
        $item = MenuItem::where('code', $menuItem)->where('is_active', true)->firstOrFail();
        $soldOut = ! $request->boolean('available');

        $item->update(['sold_out_override' => $soldOut ? true : null]);
        AuditLogger::record($soldOut ? 'Marked sold out' : 'Marked available', $item->name, $request->user());

        return redirect()->route('kitchen.display', ['drawer' => 1]);
    }
}
