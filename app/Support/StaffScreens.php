<?php

namespace App\Support;

use App\Enums\Role;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\UrlGenerationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The staff screens by name, and where a login may send someone. A "next" address is accepted only
 * when it is a path inside this app that resolves to one of these screens — never a full URL —
 * so the login can't be used to bounce people to another site.
 */
class StaffScreens
{
    /** route name => what the screen is called on the login page ("Log in to open …") */
    public const NAMES = [
        'cashier.pos.index' => 'Point of sale',
        'cashier.orders.index' => 'Orders',
        'cashier.payments.create' => 'Take payment',
        'cashier.receipt' => 'Receipt',
        'kitchen.display' => 'Kitchen display',
        'inventory.ingredients.index' => 'Stock',
        'inventory.ingredients.history' => 'Stock history',
        'admin.dashboard' => 'Dashboard',
        'admin.menu-items.index' => 'Menu & prices',
        'admin.users.index' => 'Users & roles',
        'admin.audit-logs.index' => 'Audit logs',
        'admin.tables.qr' => 'Table QR codes',
    ];

    /** The path to send a user to after logging in: a valid "next", otherwise their role's home screen. */
    public static function destination(Role $role, ?string $next): string
    {
        return self::validNext($next) ?? route($role->homeRoute(), absolute: false);
    }

    /** Screen name of a role's home, e.g. "Point of sale". */
    public static function homeName(Role $role): string
    {
        return self::NAMES[$role->homeRoute()];
    }

    /** Screen name for a path, or null when it isn't a staff screen. */
    public static function nameOf(?string $path): ?string
    {
        $name = self::routeName($path);

        return $name ? (self::NAMES[$name] ?? null) : null;
    }

    /** The path itself when it is a staff screen inside this app, otherwise null. */
    public static function validNext(?string $path): ?string
    {
        return self::nameOf($path) ? $path : null;
    }

    private static function routeName(?string $path): ?string
    {
        // Only "/something" — no scheme, no "//host", no backslashes or control characters
        if (! is_string($path) || ! preg_match('#^/(?!/)[A-Za-z0-9/_\-.?=&%]*$#', $path)) {
            return null;
        }

        try {
            return app('router')->getRoutes()->match(Request::create($path, 'GET'))->getName();
        } catch (HttpException|UrlGenerationException) {
            return null; // not a route of this app
        }
    }
}
