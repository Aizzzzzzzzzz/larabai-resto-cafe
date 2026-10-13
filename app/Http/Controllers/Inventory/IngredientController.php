<?php

namespace App\Http\Controllers\Inventory;

use App\Enums\Unit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\IngredientRequest;
use App\Services\StockUsage;
use App\Support\DemoData;
use App\Support\StockLevel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        // UI scaffold: DemoData. Phase 6 queries the ingredients table.
        $all = collect(DemoData::ingredients())->map(fn ($i) => $i + ['level' => StockLevel::of($i)]);
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
        $blocked = collect(DemoData::items())
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

    /** Phase 6: create the ingredient; opening stock becomes an ADJUST movement ("Opening stock"). */
    public function store(IngredientRequest $request): RedirectResponse
    {
        return $this->demoSaved('inventory.ingredients.index');
    }

    public function update(IngredientRequest $request, string $ingredient): RedirectResponse
    {
        DemoData::ingredient($ingredient) ?? abort(404);

        return $this->demoSaved('inventory.ingredients.index');
    }
}
