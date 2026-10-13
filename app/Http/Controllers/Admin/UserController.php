<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Catalog;
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
        $me = auth()->id();
        $users = collect(Catalog::users())->map(fn ($u) => $u + [
            'initials' => collect(explode(' ', $u['full_name']))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->join(''),
            'role_label' => Role::from($u['role'])->label(),
            'last_login' => $u['last_login_at'] ? Format::dateTime($u['last_login_at']) : null,
            'is_you' => $u['id'] === $me,
        ]);

        return view('admin.users', [
            'users' => $users,
            'roles' => Role::cases(),
            'permissions' => self::PERMISSIONS,
            'activePerRole' => $users->where('is_active', true)->countBy('role'),
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $user = User::create([
            'full_name' => trim($request->input('full_name')),
            'username' => $request->input('username'),
            'password' => $request->input('password'), // hashed by the model
            'role' => $request->input('role'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        AuditLogger::record('Added user', "{$user->full_name} · {$user->role->label()}", $request->user());

        return redirect()->route('admin.users.index')->with('status', "Added {$user->full_name}.");
    }

    public function update(UserRequest $request, string $user): RedirectResponse
    {
        $account = User::findOrFail($request->target()['id']);
        $before = $account->only(['full_name', 'username', 'role', 'is_active']);

        $account->fill([
            'full_name' => trim($request->input('full_name')),
            'username' => $request->input('username'),
            // Your own role and access can't be changed here (UserRequest refuses it), so they stay as they are
            'role' => $request->input('role', $account->role->value),
            'is_active' => $request->boolean('is_active', $account->is_active),
        ]);

        $changes = [];
        foreach ($account->getDirty() as $field => $value) {
            $changes[] = match ($field) {
                'role' => 'role '.Role::from($before['role']->value)->label().' → '.Role::from($value)->label(),
                'is_active' => $value ? 'turned on' : 'turned off',
                default => str_replace('_', ' ', $field).' changed',
            };
        }
        if ($request->filled('password')) {
            $account->password = $request->input('password');
            $changes[] = 'password reset';
        }
        $account->save();

        AuditLogger::record('Edited user', "{$account->full_name}".($changes ? ' · '.implode(', ', $changes) : ''), $request->user());

        return redirect()->route('admin.users.index')->with('status', "Saved {$account->full_name}.");
    }
}
