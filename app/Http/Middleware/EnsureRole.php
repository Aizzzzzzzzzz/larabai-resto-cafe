<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * `role:cashier,kitchen` lets those roles in (the owner/admin always gets in). Runs after `auth`.
 * An account that was switched off while its owner was signed in is signed out on the next request.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['credentials' => 'This account has been turned off. Ask the owner.']);
        }

        abort_unless($user->hasRole(...array_map(fn ($role) => Role::from($role), $roles)), 403);

        return $next($request);
    }
}
