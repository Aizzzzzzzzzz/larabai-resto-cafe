<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Catalog;
use App\Support\Showcase;
use Illuminate\View\View;

/** The café's public homepage: story, favourites, how to order at the table, where to find us. */
class HomeController extends Controller
{
    public function index(): View
    {
        $tables = collect(Catalog::tables())->where('is_active', true);

        return view('public.home', [
            'cups' => Showcase::cups(),
            // Best sellers and the newest items, unless they've run out
            'favorites' => collect(Catalog::menu())->filter(fn ($i) => ($i['is_best_seller'] || $i['is_new']) && ! Catalog::isSoldOut($i))->values(),
            'stats' => [
                'items' => count(Catalog::menu()),
                'tables' => $tables->count(),
                'seats' => $tables->sum('seats'),
            ],
        ]);
    }
}
