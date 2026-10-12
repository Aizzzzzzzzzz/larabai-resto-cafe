<?php

namespace App\Http\Controllers\Kitchen;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

/** Sold-out switches: mark a menu item sold out or available again. */
class AvailabilityController extends Controller
{
    public function update(string $menuItem): RedirectResponse
    {
        return $this->demoSaved('kitchen.display');
    }
}
