<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

/** Menu categories, edited in the Categories modal on the menu screen. */
class CategoryController extends Controller
{
    public function store(): RedirectResponse
    {
        return $this->demoSaved('admin.menu-items.index');
    }

    public function update(string $category): RedirectResponse
    {
        return $this->demoSaved('admin.menu-items.index');
    }

    public function destroy(string $category): RedirectResponse
    {
        return $this->demoSaved('admin.menu-items.index');
    }
}
