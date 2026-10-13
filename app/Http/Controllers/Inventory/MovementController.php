<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Ingredient;
use App\Services\Catalog;
use App\Services\StockUsage;
use App\Support\StockLevel;
use Illuminate\View\View;

/** Stock history of one ingredient: the last 40 movements, newest first. */
class MovementController extends Controller
{
    private const LIMIT = 40;

    public function index(string $ingredient): View
    {
        $ing = Catalog::ingredient($ingredient) ?? abort(404);

        $movements = Ingredient::where('code', $ingredient)->firstOrFail()
            ->movements()->with('user:id,full_name', 'order:id,order_no')
            ->orderByDesc('moved_at')->orderByDesc('id')
            ->limit(self::LIMIT)->get()
            ->map(fn ($m) => [
                'type' => $m->type,
                'qty' => $m->qty,
                'qty_before' => $m->qty_before,
                'qty_after' => $m->qty_after,
                'cost' => $m->cost,
                'moved_at' => $m->moved_at->toDateTimeString(),
                'reference' => $m->order?->order_no ?? '',
                'note' => $m->note,
                'user' => $m->user?->full_name ?? 'System (auto)',
            ])->all();

        return view('inventory.history', [
            'ingredient' => $ing,
            'level' => StockLevel::of($ing),
            'usedIn' => StockUsage::usedIn($ingredient),
            'movements' => $movements,
        ]);
    }
}
