<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MenuItemRequest;
use App\Http\Requests\Admin\SetAvailabilityRequest;
use App\Support\DemoData;
use App\Support\StockLevel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Menu & prices. The item editor (details, options template, ingredients per serving) and the
 * Categories list are modals on this screen.
 */
class MenuItemController extends Controller
{
    public function index(Request $request): View
    {
        $codes = array_column(DemoData::categories(), 'code');
        $cat = in_array($request->query('cat'), $codes, true) ? $request->query('cat') : 'all';
        $q = trim((string) $request->query('q'));

        // UI scaffold: DemoData. Phase 1 queries menu_items with their option groups and recipes.
        $ingredients = collect(DemoData::ingredients())->keyBy('code');
        $recipes = DemoData::recipes();
        $categories = collect(DemoData::categories())->keyBy('code');

        $items = collect(DemoData::items())->map(function ($item) use ($ingredients, $recipes, $categories) {
            $size = collect($item['options'])->firstWhere('code', 'size');
            $recipe = collect($recipes[$item['code']] ?? [])->map(fn ($r) => ['code' => $r[0], 'qty' => $r[1]]);

            return $item + [
                'category_name' => $categories[$item['category']]['name'] ?? '—',
                'large_adds' => $size ? $size['choices'][1]['price'] : null,
                'options_text' => collect($item['options'])->map(fn ($g) => $g['code'] === 'size' ? 'Size (L +₱'.$g['choices'][1]['price'].')' : $g['name'].' ('.count($g['choices']).')')->join(' · '),
                'ingredients_text' => $recipe->map(fn ($r) => $ingredients[$r['code']]['name'].' '.StockLevel::format($r['qty'], $ingredients[$r['code']]['unit']))->join(' · '),
                'availability' => match ($item['sold_out_override']) { null => 'auto', true => 'off', default => 'on' },
                'sold_now' => DemoData::isSoldOut($item),
                'recipe' => $recipe->all(),
            ];
        });

        $rows = $items
            ->filter(fn ($i) => $cat === 'all' || $i['category'] === $cat)
            ->filter(fn ($i) => $q === '' || Str::contains(Str::lower($i['name']), Str::lower($q)))
            ->values();

        return view('admin.menu', [
            'cat' => $cat,
            'q' => $q,
            'rows' => $rows,
            'categories' => $categories->values(),
            'counts' => $items->countBy('category'),
            'total' => $items->count(),
            'ingredientGroups' => $ingredients->groupBy('category'),
            'templates' => collect(DemoData::optionTemplates())->map(fn ($t) => ['label' => $t['label'], 'has_size' => collect($t['groups'])->contains(fn ($g) => $g['code'] === 'size')]),
            'firstIngredient' => $ingredients->keys()->first(),
        ]);
    }

    /** Phase 1: create the item with its option groups (from the template) and its ingredients per serving. */
    public function store(MenuItemRequest $request): RedirectResponse
    {
        return $this->demoSaved('admin.menu-items.index', ['cat' => $request->input('category')]);
    }

    public function update(MenuItemRequest $request, string $menuItem): RedirectResponse
    {
        DemoData::item($menuItem) ?? abort(404);

        return $this->demoSaved('admin.menu-items.index', ['cat' => $request->input('category')]);
    }

    public function destroy(string $menuItem): RedirectResponse
    {
        DemoData::item($menuItem) ?? abort(404);

        // Phase 1: deactivate (menu_items.is_active = 0) so past orders keep their item names and prices.
        return $this->demoSaved('admin.menu-items.index');
    }

    /** Auto (follows stock) · always available · sold out — the same switch the kitchen has. */
    public function availability(SetAvailabilityRequest $request, string $menuItem): RedirectResponse
    {
        DemoData::item($menuItem) ?? abort(404);

        return $this->demoSaved('admin.menu-items.index', array_filter(['cat' => $request->query('cat'), 'q' => $request->query('q')]));
    }
}
