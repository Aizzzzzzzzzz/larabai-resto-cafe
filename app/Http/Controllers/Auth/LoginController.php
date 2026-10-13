<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Support\CashierActions;
use App\Support\CurrentStaff;
use App\Support\DemoData;
use App\Support\StaffScreens;
use App\Support\StockLevel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Staff login (document.md §3.3): username + password, then the screen that matches the role.
 *
 * UI scaffold: the users and passwords come from DemoData (plain text, demo only) and the login is kept
 * in the session as "demo_staff". Phase 0 swaps in the users table, hashed passwords, Auth::attempt,
 * the `auth` + `role:` middleware, a "last login" stamp and the audit log entries.
 */
class LoginController extends Controller
{
    public function create(Request $request): View
    {
        $next = StaffScreens::validNext($request->query('next', $request->old('next')));
        $users = collect(DemoData::users())->where('is_active', true);
        $me = CurrentStaff::isLoggedIn() ? CurrentStaff::get() : null;

        // What is waiting right now, for the shift board
        $orders = collect(DemoData::orders());
        $hour = (int) now()->format('G');

        return view('auth.login', [
            'today' => now()->format('l, M j').' · '.now()->format('g:i A'),
            'greeting' => 'Good '.($hour < 12 ? 'morning' : ($hour < 18 ? 'afternoon' : 'evening')).'.',
            'shift' => [
                ['n' => $orders->filter(fn ($o) => CashierActions::unpaid($o))->count(), 'label' => 'waiting for payment'],
                ['n' => $orders->whereIn('status', ['paid', 'preparing', 'ready'])->count(), 'label' => 'in the kitchen'],
                ['n' => collect(DemoData::ingredients())->filter(fn ($i) => StockLevel::of($i) !== StockLevel::OK)->count(), 'label' => 'low on stock'],
            ],
            'next' => $next,
            'nextName' => StaffScreens::nameOf($next),
            'me' => $me,
            'meRole' => $me ? Role::from($me['role']) : null,
            'meGoName' => $me ? StaffScreens::nameOf(StaffScreens::destination(Role::from($me['role']), $next)) : null,
            'meGoUrl' => $me ? StaffScreens::destination(Role::from($me['role']), $next) : null,
            // Demo accounts (with their passwords) are shown only in demo mode
            'accounts' => config('cafe.demo_mode') ? $users->map(fn ($u) => $u + [
                'initials' => collect(explode(' ', $u['full_name']))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->join(''),
                'role_label' => Role::from($u['role'])->label(),
                'opens' => StaffScreens::homeName(Role::from($u['role'])),
            ])->values() : collect(),
            // Coming from a role card: fill in that role's username
            'prefill' => old('username', $users->firstWhere('role', $request->query('role'))['username'] ?? ''),
        ]);
    }

    /** Phase 0: Auth::attempt + throttle, "last login", audit "Logged in" (or "Failed login"). */
    public function store(LoginRequest $request): RedirectResponse
    {
        $user = collect(DemoData::users())->first(
            fn ($u) => $u['is_active'] && $u['username'] === $request->input('username') && hash_equals($u['password'], $request->input('password')),
        );

        if (! $user) {
            // One message for a wrong username, a wrong password and a disabled account, so it doesn't reveal which exist
            return back()->withInput($request->only('username', 'next'))
                ->withErrors(['credentials' => 'That username and password don’t match an active account. Check them, or pick a demo account below.']);
        }

        CurrentStaff::login($user);

        return redirect(StaffScreens::destination(Role::from($user['role']), $request->input('next')));
    }

    public function destroy(Request $request): RedirectResponse
    {
        CurrentStaff::logout();

        return redirect()->route('login')->with('status', 'You’re logged out.');
    }
}
