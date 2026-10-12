<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

/** Add a delivery (PURCHASE) or set the counted amount (ADJUST). */
class RestockController extends Controller
{
    public function store(string $ingredient): RedirectResponse
    {
        return $this->demoSaved('inventory.ingredients.index');
    }
}
