<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/** Stock history of one ingredient. */
class MovementController extends Controller
{
    public function index(string $ingredient): View
    {
        return view('inventory.history', ['ingredient' => $ingredient]);
    }
}
