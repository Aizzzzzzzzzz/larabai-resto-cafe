<?php

namespace App\Http\Controllers\Kitchen;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kitchen\AvailabilityRequest;
use App\Support\DemoData;
use Illuminate\Http\RedirectResponse;

/** Sold-out switches: mark a menu item sold out, or available again. */
class AvailabilityController extends Controller
{
    public function update(AvailabilityRequest $request, string $menuItem): RedirectResponse
    {
        DemoData::item($menuItem) ?? abort(404);

        // Phase 5: set menu_items.sold_out_override; the customer menu and POS follow at once.
        return $this->demoSaved('kitchen.display', ['drawer' => 1]);
    }
}
