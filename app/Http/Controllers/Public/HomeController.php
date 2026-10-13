<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\DemoData;
use App\Support\Showcase;
use Illuminate\View\View;

/** The café's public homepage: story, favourites, how to order at the table, where to find us. */
class HomeController extends Controller
{
    public function index(): View
    {
        // UI scaffold: DemoData. Phase 1 reads the menu and the tables from the database.
        $tables = collect(DemoData::tables())->where('is_active', true);

        return view('public.home', [
            'cups' => Showcase::cups(),
            // Best sellers and the newest items, unless they've run out
            'favorites' => collect(DemoData::items())->filter(fn ($i) => ($i['is_best_seller'] || $i['is_new']) && ! DemoData::isSoldOut($i))->values(),
            'stats' => [
                'items' => count(DemoData::items()),
                'tables' => $tables->count(),
                'seats' => $tables->sum('seats'),
            ],
        ]);
    }
}
