<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\AuditArea;
use Illuminate\Support\Facades\Auth;

/**
 * Writes the audit log (document.md §3.7, §4.17): one line for each business action — a payment, a void,
 * a menu change, a restock, a login. Services and controllers call it explicitly at the moment the action
 * happens; it is not a mirror of table changes.
 */
class AuditLogger
{
    /** Logs an action by the signed-in staff member (or by $by). */
    public static function record(string $action, string $detail = '', ?User $by = null): AuditLog
    {
        $by ??= Auth::user();

        return self::write($action, $detail, $by?->id, $by?->full_name ?? 'System', $by?->role->value ?? 'system');
    }

    /** Logs an action the customer took from their phone (placing, paying or cancelling a QR order). */
    public static function customer(string $action, string $detail = ''): AuditLog
    {
        return self::write($action, $detail, null, 'Customer (QR)', 'customer');
    }

    /** Logs something with no signed-in person, such as a failed login. */
    public static function anonymous(string $action, string $detail = ''): AuditLog
    {
        return self::write($action, $detail, null, 'Not signed in', 'system');
    }

    private static function write(string $action, string $detail, ?int $userId, string $name, string $role): AuditLog
    {
        return AuditLog::create([
            'user_id' => $userId,
            'user_name' => $name,
            'role' => $role,
            'module' => AuditArea::of($action)[0],
            'action' => $action,
            'detail' => $detail,
            'logged_at' => now(),
        ]);
    }
}
