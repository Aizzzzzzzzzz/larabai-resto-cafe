<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Support\CurrentStaff;
use App\Support\DemoData;
use App\Support\Format;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Users & roles. Add / edit happen in one modal on the list: name, username, role, a new password
 * and "can log in" (deactivating keeps the account and its history, but blocks login).
 */
class UserController extends Controller
{
    /** What each role can do (document.md §3.3): admin, cashier, kitchen, inventory. */
    private const PERMISSIONS = [
        ['Take QR orders from customers', 1, 1, 0, 0],
        ['Point of sale and taking payments', 1, 1, 0, 0],
        ['Senior/PWD discounts, cancel and void orders', 1, 1, 0, 0],
        ['Kitchen display, mark items sold out', 1, 0, 1, 0],
        ['Stock levels and restocking', 1, 0, 0, 1],
        ['Sales dashboard', 1, 0, 0, 0],
        ['Menu, prices and ingredients', 1, 0, 0, 0],
        ['Users and audit logs', 1, 0, 0, 0],
    ];

    public function index(): View
    {
        // UI scaffold: DemoData. Phase 0 queries the users table.
        $me = CurrentStaff::get()['name'];
        $users = collect(DemoData::users())->map(fn ($u) => $u + [
            'initials' => collect(explode(' ', $u['full_name']))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->join(''),
            'role_label' => Role::from($u['role'])->label(),
            'last_login' => $u['last_login_at'] ? Format::dateTime($u['last_login_at']) : null,
            'is_you' => $u['full_name'] === $me,
        ]);

        return view('admin.users', [
            'users' => $users,
            'roles' => Role::cases(),
            'permissions' => self::PERMISSIONS,
            'activePerRole' => $users->where('is_active', true)->countBy('role'),
        ]);
    }

    /** Phase 0: create the user with a hashed password; audit "Added user". */
    public function store(UserRequest $request): RedirectResponse
    {
        return $this->demoSaved('admin.users.index');
    }

    /** Phase 0: save the changes (hash a new password), audit "Edited user" with what changed. */
    public function update(UserRequest $request, string $user): RedirectResponse
    {
        $request->target() ?? abort(404);

        return $this->demoSaved('admin.users.index');
    }
}
