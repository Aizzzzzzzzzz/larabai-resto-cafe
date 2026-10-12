<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Stock list; add / edit ingredient happen in modals on the list. */
class IngredientController extends Controller
{
    public function index(): View
    {
        return view('inventory.ingredients');
    }

    public function store(): RedirectResponse
    {
        return $this->demoSaved('inventory.ingredients.index');
    }

    public function update(string $ingredient): RedirectResponse
    {
        return $this->demoSaved('inventory.ingredients.index');
    }
}
