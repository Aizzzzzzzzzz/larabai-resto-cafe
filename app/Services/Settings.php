<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Settings the owner edits in the admin screens, kept in the `settings` table (key → value).
 * Reading never fails: if the database is down or the table doesn't exist yet, the default is returned, so
 * the error pages and a half-set-up install still draw the header.
 */
class Settings
{
    private static ?array $all = null;

    public static function get(string $key, ?string $default = null): ?string
    {
        try {
            self::$all ??= DB::table('settings')->pluck('value', 'key')->all();
        } catch (Throwable) {
            return $default;
        }

        return self::$all[$key] ?? $default;
    }

    /** Saves a value; null removes the setting. */
    public static function set(string $key, ?string $value): void
    {
        if ($value === null) {
            DB::table('settings')->where('key', $key)->delete();
        } else {
            DB::table('settings')->upsert([['key' => $key, 'value' => $value, 'created_at' => now(), 'updated_at' => now()]], ['key'], ['value', 'updated_at']);
        }

        self::flush();
    }

    public static function flush(): void
    {
        self::$all = null;
    }
}
