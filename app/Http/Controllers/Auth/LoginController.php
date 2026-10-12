<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        // UI scaffold: no real login yet (Phase 0).
        return $this->demoSaved('admin.dashboard');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget('demo_staff');

        return $this->demoSaved('login');
    }
}
