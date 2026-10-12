<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Menu & prices. The item editor (options + ingredients per serving) is a modal on the list. */
class MenuItemController extends Controller
{
    public function index(): View
    {
        return view('admin.menu');
    }

    public function store(): RedirectResponse
    {
        return $this->demoSaved('admin.menu-items.index');
    }

    public function update(string $menuItem): RedirectResponse
    {
        return $this->demoSaved('admin.menu-items.index');
    }

    public function destroy(string $menuItem): RedirectResponse
    {
        return $this->demoSaved('admin.menu-items.index');
    }
}
