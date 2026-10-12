<?php

namespace App\Support;

use App\Enums\Role;

/**
 * Who is using a staff screen. UI scaffold: the demo login's choice (session "demo_staff"),
 * defaulting to the owner. Phase 0 replaces this with auth()->user().
 */
class CurrentStaff
{
    /** @return array{name: string, role: string} */
    public static function get(): array
    {
        return session('demo_staff', ['name' => 'Maria Dizon', 'role' => Role::Admin->value]);
    }

    public static function role(): Role
    {
        return Role::from(self::get()['role']);
    }
}
