<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\QrCodes;
use App\Support\DemoData;
use Illuminate\View\View;

/** Printable QR standees: one per table, plus the counter for take-out and delivery. */
class TableQrController extends Controller
{
    public function index(): View
    {
        // UI scaffold: DemoData tables. Phase 1 reads dining_tables (active ones).
        $cards = collect(DemoData::tables())->map(fn ($t) => [
            'title' => 'Table '.$t['table_no'],
            'sub' => $t['seats'].' seats · dine-in',
            'url' => $url = QrCodes::tableUrl($t['table_no']),
            'svg' => QrCodes::svg($url),
        ])->push([
            'title' => 'Counter',
            'sub' => 'Take-out and delivery',
            'url' => $url = QrCodes::counterUrl(),
            'svg' => QrCodes::svg($url),
        ]);

        return view('admin.table-qr', ['cards' => $cards]);
    }
}
