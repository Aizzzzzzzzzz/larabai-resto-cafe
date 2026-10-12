<?php

namespace App\Http\Controllers\Kitchen;

use App\Http\Controllers\Controller;
use App\Support\CurrentStaff;
use App\Support\DemoData;
use App\Support\KitchenActions;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DisplayController extends Controller
{
    public function index(Request $request): View
    {
        $items = array_map(fn ($i) => $i + ['sold_out' => DemoData::isSoldOut($i)], DemoData::items());

        return view('kitchen.display', self::boardData() + [
            'staff' => CurrentStaff::get(),
            'categories' => DemoData::categories(),
            'menu' => collect($items)->groupBy('category'),
            'drawerOpen' => $request->boolean('drawer'),
        ]);
    }

    /** The board alone, re-fetched by the display every few seconds. */
    public function board(): View
    {
        return view('kitchen.partials.board', self::boardData());
    }

    /** Columns, header stats and the recently-done strip. UI scaffold: today's sample orders. */
    private static function boardData(): array
    {
        $orders = collect(DemoData::orders());
        $live = $orders->filter(fn ($o) => KitchenActions::onBoard($o))
            ->sortBy(fn ($o) => KitchenActions::startedAt($o))
            ->groupBy(fn ($o) => KitchenActions::column($o));

        // Average minutes from reaching the kitchen to Ready, today
        $prep = $orders->map(function ($o) {
            $ready = collect($o['status_log'])->firstWhere('status', 'ready');

            return $ready ? Carbon::parse(KitchenActions::startedAt($o))->diffInMinutes(Carbon::parse($ready['at'])) : null;
        })->filter(fn ($m) => $m !== null);

        $completed = $orders->where('status', 'completed');

        return [
            'columns' => ['new' => $live->get('new', collect()), 'prep' => $live->get('prep', collect()), 'ready' => $live->get('ready', collect())],
            'stats' => [
                'queue' => $live->flatten(1)->count(),
                'avgPrep' => $prep->isNotEmpty() ? number_format($prep->avg(), 1).' min' : '–',
                'done' => $completed->count(),
            ],
            'recent' => $completed->sortByDesc(fn ($o) => collect($o['status_log'])->last()['at'])->take(6)->values(),
        ];
    }
}
