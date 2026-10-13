<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Services\StockUsage;
use App\Support\DemoData;
use App\Support\DemoMovements;
use App\Support\StockLevel;
use Illuminate\View\View;

/** Stock history of one ingredient: the last 40 movements, newest first. */
class MovementController extends Controller
{
    public function index(string $ingredient): View
    {
        $ing = DemoData::ingredient($ingredient) ?? abort(404);

        return view('inventory.history', [
            'ingredient' => $ing,
            'level' => StockLevel::of($ing),
            'usedIn' => StockUsage::usedIn($ingredient),
            'movements' => DemoMovements::for($ingredient),
        ]);
    }
}
