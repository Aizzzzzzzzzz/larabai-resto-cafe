<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Danger zone: restore the sample data (demo mode only). Preview → type RESET → execute. */
class DemoResetController extends Controller
{
    public function show(): View
    {
        abort_unless(config('cafe.demo_mode'), 404);

        return view('admin.demo-reset');
    }

    public function store(): RedirectResponse
    {
        abort_unless(config('cafe.demo_mode'), 404);

        return $this->demoSaved('admin.dashboard');
    }
}
