<?php

namespace App\Http\Controllers\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\Catalog;
use App\Services\Orders;
use App\Support\CashierActions;
use App\Support\DemoAccounts;
use App\Support\StaffScreens;
use App\Support\StockLevel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Staff login (document.md §3.3): username + password, then the screen that matches the role.
 * Passwords are stored hashed; sign-ins, sign-outs and failed attempts go to the audit log (LogAuthActivity).
 */
class LoginController extends Controller
{
    public function create(Request $request): View
    {
        $next = StaffScreens::validNext($request->query('next', $request->old('next')));
        $users = collect(Catalog::users())->where('is_active', true);
        $me = Auth::user();

        // What is waiting right now, for the shift board
        $orders = collect(Orders::today());
        $hour = (int) now()->format('G');

        return view('auth.login', [
            'today' => now()->format('l, M j').' · '.now()->format('g:i A'),
            'greeting' => 'Good '.($hour < 12 ? 'morning' : ($hour < 18 ? 'afternoon' : 'evening')).'.',
            'shift' => [
                ['n' => $orders->filter(fn ($o) => CashierActions::unpaid($o))->count(), 'label' => 'waiting for payment'],
                ['n' => $orders->whereIn('status', ['paid', 'preparing', 'ready'])->count(), 'label' => 'in the kitchen'],
                ['n' => collect(Catalog::ingredients())->filter(fn ($i) => StockLevel::of($i) !== StockLevel::OK)->count(), 'label' => 'low on stock'],
            ],
            'next' => $next,
            'nextName' => StaffScreens::nameOf($next),
            'me' => $me ? ['name' => $me->full_name, 'role' => $me->role->value] : null,
            'meRole' => $me?->role,
            'meGoName' => $me ? StaffScreens::nameOf(StaffScreens::destination($me->role, $next)) : null,
            'meGoUrl' => $me ? StaffScreens::destination($me->role, $next) : null,
            'accounts' => config('cafe.demo_mode') ? $this->demoAccounts($users) : collect(),
            // Coming from a role card: fill in that role's username
            'prefill' => old('username', $users->firstWhere('role', $request->query('role'))['username'] ?? ''),
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $credentials = ['username' => $request->input('username'), 'password' => $request->input('password'), 'is_active' => true];

        if (! Auth::attempt($credentials)) {
            // One message for a wrong username, a wrong password and a disabled account, so it doesn't reveal which exist
            return back()->withInput($request->only('username', 'next'))
                ->withErrors(['credentials' => config('cafe.demo_mode')
                    ? 'That username and password don’t match an active account. Check them, or pick a demo account below.'
                    : 'That username and password don’t match an active account. Check them and try again.']);
        }

        $request->session()->regenerate(); // a new session id at login, against session fixation

        return redirect(StaffScreens::destination(Auth::user()->role, $request->input('next')));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'You’re logged out.');
    }

    /**
     * The one-click demo accounts. A password is offered only while it still is the seeded one,
     * so a password the owner has since changed is never shown.
     */
    private function demoAccounts($users)
    {
        $hashes = User::pluck('password', 'username');

        return $users->map(function ($u) use ($hashes) {
            $demo = DemoAccounts::password($u['username']);

            return $u + [
                'initials' => collect(explode(' ', $u['full_name']))->map(fn ($p) => mb_substr($p, 0, 1))->take(2)->join(''),
                'role_label' => Role::from($u['role'])->label(),
                'opens' => StaffScreens::homeName(Role::from($u['role'])),
                'password' => $demo && Hash::check($demo, $hashes[$u['username']] ?? '') ? $demo : '',
            ];
        })->values();
    }
}
