<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MenuItemRequest;
use App\Http\Requests\Admin\SetAvailabilityRequest;
use App\Models\MenuItem;
use App\Services\AuditLogger;
use App\Services\Catalog;
use App\Services\MenuBuilder;
use App\Services\MenuImages;
use App\Support\Format;
use App\Support\OptionTemplates;
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
    /** Artwork colours of a new item, by category (the mockup draws every item in two tones). */
    private const TONES = [
        'coffee' => ['#4A2E1C', '#9C6B45'],
        'noncoffee' => ['#4B2A20', '#9E6650'],
        'milktea' => ['#9A7A4E', '#E3CFA6'],
        'frappe' => ['#3A2418', '#7F5641'],
        'meals' => ['#8A3B22', '#E09A63'],
        'pastries' => ['#B07A2E', '#F2D49B'],
    ];

    public function index(Request $request): View
    {
        $codes = array_column(Catalog::categories(), 'code');
        $cat = in_array($request->query('cat'), $codes, true) ? $request->query('cat') : 'all';
        $q = trim((string) $request->query('q'));

        $ingredients = collect(Catalog::ingredients())->keyBy('code');
        $recipes = Catalog::recipes();
        $categories = collect(Catalog::categories())->keyBy('code');

        $items = collect(Catalog::menu())->map(function ($item) use ($ingredients, $recipes, $categories) {
            $size = collect($item['options'])->firstWhere('code', 'size');
            $recipe = collect($recipes[$item['code']] ?? [])->map(fn ($r) => ['code' => $r[0], 'qty' => $r[1]]);

            return $item + [
                'category_name' => $categories[$item['category']]['name'] ?? '—',
                'large_adds' => $size ? $size['choices'][1]['price'] : null,
                'options_text' => collect($item['options'])->map(fn ($g) => $g['code'] === 'size' ? 'Size (L +₱'.$g['choices'][1]['price'].')' : $g['name'].' ('.count($g['choices']).')')->join(' · '),
                'ingredients_text' => $recipe->map(fn ($r) => $ingredients[$r['code']]['name'].' '.StockLevel::format($r['qty'], $ingredients[$r['code']]['unit']))->join(' · '),
                'availability' => match ($item['sold_out_override']) {
                    null => 'auto', true => 'off', default => 'on'
                },
                'sold_now' => Catalog::isSoldOut($item),
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
            'templates' => collect(OptionTemplates::all())->map(fn ($t) => ['label' => $t['label'], 'has_size' => collect($t['groups'])->contains(fn ($g) => $g['code'] === 'size')]),
            'firstIngredient' => $ingredients->keys()->first(),
        ]);
    }

    /** Creates the item with its option groups (from the template) and its ingredients per serving. */
    public function store(MenuItemRequest $request, MenuBuilder $menu, MenuImages $images): RedirectResponse
    {
        $groups = OptionTemplates::all()[$request->input('template')]['groups'];

        // "Large adds ₱…" sets the Large choice of the size group
        $groups = array_map(function ($group) use ($request) {
            if ($group['code'] === 'size') {
                $group['choices'][1]['price'] = (float) $request->input('large');
            }

            return $group;
        }, $groups);

        $item = $menu->create([
            'category' => $request->input('category'),
            'name' => trim($request->input('name')),
            'description' => $request->input('description'),
            'price' => $request->input('price'),
            'tone' => self::TONES[$request->input('category')] ?? ['#6E5240', '#D8C3AE'],
            'is_best_seller' => $request->boolean('is_best_seller'),
            'is_new' => $request->boolean('is_new'),
            'image_path' => $request->hasFile('image') ? $images->store($request->file('image')) : null,
        ], $groups, $request->recipe());

        AuditLogger::record('Added menu item', "{$item->name} · ".Format::peso($item->price), $request->user());

        return redirect()->route('admin.menu-items.index', ['cat' => $request->input('category')])->with('status', "Added {$item->name} to the menu.");
    }

    public function update(MenuItemRequest $request, MenuBuilder $menu, MenuImages $images, string $menuItem): RedirectResponse
    {
        $item = $this->find($menuItem);
        $before = $item->only(['name', 'price']);
        $oldImage = $item->image_path;

        // A new file replaces the photo; the "remove" box takes it away; otherwise it stays
        $photo = ['image_path' => $request->hasFile('image') ? $images->store($request->file('image')) : ($request->boolean('remove_image') ? null : $oldImage)];

        $menu->update($item, [
            'category' => $request->input('category'),
            'name' => trim($request->input('name')),
            'description' => $request->input('description'),
            'price' => $request->input('price'),
            'is_best_seller' => $request->boolean('is_best_seller'),
            'is_new' => $request->boolean('is_new'),
        ] + $photo, $request->input('large') === null ? null : (float) $request->input('large'), $request->recipe());

        if ($photo['image_path'] !== $oldImage) {
            $images->delete($oldImage);
        }

        $item->refresh();
        $detail = $item->name;
        if ((float) $before['price'] !== $item->price) {
            $detail .= ' · price '.Format::peso($before['price']).' → '.Format::peso($item->price);
        }
        if ($photo['image_path'] !== $oldImage) {
            $detail .= $photo['image_path'] ? ' · photo changed' : ' · photo removed';
        }
        if ($before['name'] !== $item->name) {
            $detail = "{$before['name']} → {$detail}";
        }
        AuditLogger::record('Edited menu item', $detail, $request->user());

        return redirect()->route('admin.menu-items.index', ['cat' => $request->input('category')])->with('status', "Saved {$item->name}.");
    }

    /**
     * Takes an item off the menu. One that was never ordered is deleted outright; one with sales is only
     * switched off, so past orders and receipts keep its name and price.
     */
    public function destroy(Request $request, MenuImages $images, string $menuItem): RedirectResponse
    {
        $item = $this->find($menuItem);

        if ($item->orderItems()->exists()) {
            $item->update(['is_active' => false]);
        } else {
            $item->delete();
            $images->delete($item->image_path); // nothing refers to it any more
        }
        AuditLogger::record('Removed menu item', $item->name, $request->user());

        return redirect()->route('admin.menu-items.index')->with('status', "Removed {$item->name} from the menu.");
    }

    /** Auto (follows stock) · always available · sold out — the same switch the kitchen has. */
    public function availability(SetAvailabilityRequest $request, string $menuItem): RedirectResponse
    {
        $item = $this->find($menuItem);
        $mode = $request->input('availability');

        $item->update(['sold_out_override' => match ($mode) {
            'off' => true, 'on' => false, default => null
        }]);
        AuditLogger::record($mode === 'off' ? 'Marked sold out' : 'Marked available', $item->name.($mode === 'on' ? ' · always available' : ''), $request->user());

        return redirect()->route('admin.menu-items.index', array_filter(['cat' => $request->query('cat'), 'q' => $request->query('q')]))
            ->with('status', "{$item->name}: ".['auto' => 'follows stock', 'on' => 'always available', 'off' => 'sold out'][$mode].'.');
    }

    private function find(string $code): MenuItem
    {
        return MenuItem::where('code', $code)->where('is_active', true)->first() ?? abort(404);
    }
}
