<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\DemoReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** Danger zone: restore the sample data (demo mode only). Preview → type RESET → execute. */
class DemoResetController extends Controller
{
    public function show(DemoReset $reset): View
    {
        abort_unless(config('cafe.demo_mode'), 404);

        return view('admin.demo-reset', ['counts' => $reset->counts()]);
    }

    /**
     * Typing the word is only convenience — it's checked here again. The database is rebuilt from the seeders,
     * which also signs everyone out (the accounts are recreated), so the owner signs in again.
     */
    public function store(Request $request, DemoReset $reset): RedirectResponse
    {
        abort_unless(config('cafe.demo_mode'), 404);

        $request->validate(['confirm' => ['required', 'in:RESET']], ['confirm.in' => 'Type RESET exactly to continue.', 'confirm.required' => 'Type RESET exactly to continue.']);

        $reset->run();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Demo data restored. Sign in again.');
    }
}
