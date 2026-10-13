<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/** Who is using a staff screen: the signed-in account, in the small array form the screens use. */
class CurrentStaff
{
    public static function user(): User
    {
        return Auth::user() ?? abort(401);
    }

    /** @return array{id: int, name: string, role: string, username: string} */
    public static function get(): array
    {
        $user = self::user();

        return ['id' => $user->id, 'name' => $user->full_name, 'username' => $user->username, 'role' => $user->role->value];
    }

    public static function role(): Role
    {
        return self::user()->role;
    }

    public static function isLoggedIn(): bool
    {
        return Auth::check();
    }
}
