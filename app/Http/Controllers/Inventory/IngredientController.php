<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\Unit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\IngredientRequest;
use App\Models\Ingredient;
use App\Services\AuditLogger;
use App\Services\Catalog;
use App\Services\StockService;
use App\Services\StockUsage;
use App\Support\StockLevel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Stock list; add / edit ingredient happen in modals on the list. */
class IngredientController extends Controller
{
    public const TABS = ['all' => 'All', 'low' => 'Low or out', 'ok' => 'In stock'];

    public function index(Request $request): View
    {
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'all';
        $category = (string) $request->query('cat');
        $q = trim((string) $request->query('q'));

        $all = collect(Catalog::ingredients())->map(fn ($i) => $i + ['level' => StockLevel::of($i)]);
        $count = fn (callable $test) => $all->filter($test)->count();
        $low = $count(fn ($i) => $i['level'] === StockLevel::LOW);
        $out = $count(fn ($i) => $i['level'] === StockLevel::OUT);

        $rows = $all
            ->filter(fn ($i) => match ($tab) {
                'low' => $i['level'] !== StockLevel::OK,
                'ok' => $i['level'] === StockLevel::OK,
                default => true,
            })
            ->filter(fn ($i) => $category === '' || $i['category'] === $category)
            ->filter(fn ($i) => $q === '' || Str::contains(Str::lower($i['name']), Str::lower($q)))
            ->sortBy(fn ($i) => StockLevel::rank($i['level']))
            ->values()
            ->map(fn ($i) => $i + ['used_in' => count(StockUsage::usedIn($i['code']))]);

        // Menu items customers can't order because an ingredient ran out
        $names = $all->pluck('name', 'code');
        $blocked = collect(Catalog::menu())
            ->map(fn ($item) => ['name' => $item['name'], 'needs' => array_map(fn ($c) => $names[$c], StockUsage::shortFor($item))])
            ->filter(fn ($b) => $b['needs'])
            ->values();

        return view('inventory.ingredients', [
            'tab' => $tab,
            'category' => $category,
            'q' => $q,
            'rows' => $rows,
            'categories' => $all->pluck('category')->unique()->values(),
            'counts' => ['all' => $all->count(), 'low' => $low + $out, 'ok' => $all->count() - $low - $out],
            'kpis' => [
                'tracked' => $all->count(),
                'low' => $low,
                'out' => $out,
                'value' => $all->sum(fn ($i) => max(0, $i['stock_qty']) * $i['unit_cost']),
            ],
            'blocked' => $blocked,
            'units' => Unit::cases(),
        ]);
    }

    /** Creates the ingredient; opening stock becomes an ADJUST movement ("Opening stock"). */
    public function store(IngredientRequest $request, StockService $stock): RedirectResponse
    {
        $ingredient = DB::transaction(function () use ($request, $stock) {
            $ingredient = Ingredient::create([
                'code' => $this->uniqueCode($request->input('name')),
                'name' => trim($request->input('name')),
                'category' => trim($request->input('category')),
                'unit' => $request->input('unit'),
                'stock_qty' => 0,
                'reorder_level' => $request->input('reorder_level'),
                'restock_qty' => $request->input('restock_qty'),
                'unit_cost' => $request->input('unit_cost'),
            ]);
            $stock->open($ingredient, (float) $request->input('opening_stock', 0), $request->user());

            return $ingredient;
        });

        AuditLogger::record('Added ingredient', "{$ingredient->name} · {$ingredient->unit->value}", $request->user());

        return redirect()->route('inventory.ingredients.index')->with('status', "Added {$ingredient->name}.");
    }

    /** Changes the name, category and reorder numbers. The unit and the stock itself are not edited here. */
    public function update(IngredientRequest $request, string $ingredient): RedirectResponse
    {
        $ing = Ingredient::where('code', $ingredient)->firstOrFail();

        $changes = [
            'name' => trim($request->input('name')),
            'category' => trim($request->input('category')),
            'reorder_level' => (float) $request->input('reorder_level'),
            'restock_qty' => (float) $request->input('restock_qty'),
            'unit_cost' => (float) $request->input('unit_cost'),
        ];
        $ing->fill($changes);
        $what = collect($ing->getDirty())->keys()->map(fn ($field) => str_replace('_', ' ', $field))->join(', ');
        $ing->save();

        if ($what !== '') {
            AuditLogger::record('Edited ingredient', "{$ing->name} · changed {$what}", $request->user());
        }

        return redirect()->route('inventory.ingredients.index')->with('status', "Saved {$ing->name}.");
    }

    /** "Oat milk" → oat-milk (and -2, -3… if that code is taken). */
    private function uniqueCode(string $name): string
    {
        $base = Str::slug($name) ?: 'ingredient';
        $code = $base;

        for ($n = 2; Ingredient::where('code', $code)->exists(); $n++) {
            $code = $base.'-'.$n;
        }

        return $code;
    }
}
