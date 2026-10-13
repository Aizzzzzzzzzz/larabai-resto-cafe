<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\RestockRequest;
use App\Models\Ingredient;
use App\Services\AuditLogger;
use App\Services\StockService;
use App\Support\StockLevel;
use Illuminate\Http\RedirectResponse;

/** Add a delivery (PURCHASE) or set the counted amount (ADJUST). */
class RestockController extends Controller
{
    public function store(RestockRequest $request, StockService $stock, string $ingredient): RedirectResponse
    {
        $ing = Ingredient::where('code', $ingredient)->firstOrFail();
        $qty = round((float) $request->input('qty'), 2);
        $note = $request->input('note');
        $unit = $ing->unit->value;

        if ($request->input('mode') === 'count') {
            // A count that matches the books changes nothing, so it leaves no movement
            $move = $stock->count($ing, $qty, $note, $request->user());

            if (! $move) {
                return redirect()->route('inventory.ingredients.index')->with('status', 'Count matches. Nothing to change.');
            }

            AuditLogger::record('Stock count', "{$ing->name} · ".StockLevel::format($move->qty_before, $unit).' → '.StockLevel::format($move->qty_after, $unit).($note ? " · {$note}" : ''), $request->user());

            return redirect()->route('inventory.ingredients.index')->with('status', "{$ing->name} set to ".StockLevel::format($move->qty_after, $unit).'.');
        }

        $move = $stock->restock($ing, $qty, $note, $request->user());
        AuditLogger::record('Restocked', "{$ing->name} · +".StockLevel::format($qty, $unit).($note ? " · {$note}" : ''), $request->user());

        return redirect()->route('inventory.ingredients.index')->with('status', 'Added '.StockLevel::format($qty, $unit)." of {$ing->name}. Now ".StockLevel::format($move->qty_after, $unit).'.');
    }
}
