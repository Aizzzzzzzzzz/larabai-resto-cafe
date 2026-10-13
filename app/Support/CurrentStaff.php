<?php

namespace App\Support;

use App\Enums\Role;

/**
 * Who is using a staff screen. UI scaffold: the demo login's choice (session "demo_staff"),
 * defaulting to the owner so every screen stays browsable. Phase 0 replaces this with
 * auth()->user() and the routes get auth + role middleware.
 */
class CurrentStaff
{
    private const KEY = 'demo_staff';

    /** @return array{name: string, role: string, username?: string} */
    public static function get(): array
    {
        return session(self::KEY, ['name' => 'Maria Dizon', 'role' => Role::Admin->value]);
    }

    public static function role(): Role
    {
        return Role::from(self::get()['role']);
    }

    /** Whether someone has logged in through the demo login (as opposed to the owner default). */
    public static function isLoggedIn(): bool
    {
        return session()->has(self::KEY);
    }

    /** @param  array{id: int, full_name: string, username: string, role: string}  $user */
    public static function login(array $user): void
    {
        session()->regenerate(); // a new session id at login, against session fixation
        session([self::KEY => ['id' => $user['id'], 'name' => $user['full_name'], 'username' => $user['username'], 'role' => $user['role']]]);
    }

    public static function logout(): void
    {
        session()->forget(self::KEY);
        session()->regenerate();
    }
}
