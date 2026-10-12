<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Users & roles. Add / edit happen in a modal on the list. */
class UserController extends Controller
{
    public function index(): View
    {
        return view('admin.users');
    }

    public function store(): RedirectResponse
    {
        return $this->demoSaved('admin.users.index');
    }

    public function update(string $user): RedirectResponse
    {
        return $this->demoSaved('admin.users.index');
    }

    public function password(string $user): RedirectResponse
    {
        return $this->demoSaved('admin.users.index');
    }

    /** Deactivate or reactivate an account. */
    public function active(string $user): RedirectResponse
    {
        return $this->demoSaved('admin.users.index');
    }
}
