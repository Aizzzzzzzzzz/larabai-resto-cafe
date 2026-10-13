<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\DemoAudit;
use App\Support\DemoData;
use App\Support\DemoMovements;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Danger zone: restore the sample data (demo mode only). Preview → type RESET → execute. */
class DemoResetController extends Controller
{
    public function show(): View
    {
        abort_unless(config('cafe.demo_mode'), 404);

        $orders = collect(DemoData::orders());

        // UI scaffold: counts of the sample records. Phase 9 counts the real rows.
        return view('admin.demo-reset', ['counts' => [
            'orders' => $orders->count(),
            'payments' => $orders->whereNotNull('payment')->count(),
            'Senior/PWD discount records' => $orders->whereNotNull('discount')->count(),
            'stock movements' => collect(DemoMovements::all())->flatten(1)->count(),
            'audit log entries' => count(DemoAudit::all()),
        ]]);
    }

    /**
     * Typing the word is only convenience — it's checked here again. Phase 9: re-count the rows,
     * then run migrate:fresh --seed.
     */
    public function store(Request $request): RedirectResponse
    {
        abort_unless(config('cafe.demo_mode'), 404);

        $request->validate(['confirm' => ['required', 'in:RESET']], ['confirm.in' => 'Type RESET exactly to continue.', 'confirm.required' => 'Type RESET exactly to continue.']);

        return $this->demoSaved('admin.dashboard');
    }
}
