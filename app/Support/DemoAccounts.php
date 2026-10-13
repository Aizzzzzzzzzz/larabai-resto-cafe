<?php

namespace App\Support;

use Database\Seeders\Data\SampleData;

/**
 * The demo accounts' passwords, which are shown (demo mode only) on the login page and the navigator so a
 * presenter can sign in with one click. Only the hashes are stored; this class knows what they were seeded as.
 */
class DemoAccounts
{
    public static function password(string $username): ?string
    {
        return collect(SampleData::users())->firstWhere('username', $username)['password'] ?? null;
    }
}
