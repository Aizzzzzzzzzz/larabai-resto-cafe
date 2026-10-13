<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

/** Sign-ins, sign-outs and failed attempts go to the audit log; a sign-in also stamps "last login". */
class LogAuthActivity
{
    public function onLogin(Login $event): void
    {
        /** @var User $user */
        $user = $event->user;
        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        AuditLogger::record('Logged in', "{$user->full_name} ({$user->role->value})", $user);
    }

    public function onLogout(Logout $event): void
    {
        if ($event->user instanceof User) {
            AuditLogger::record('Logged out', "{$event->user->full_name} ({$event->user->role->value})", $event->user);
        }
    }

    public function onFailed(Failed $event): void
    {
        // Only the username is written down — never the password that was tried
        AuditLogger::anonymous('Failed login', 'Username: '.mb_substr((string) ($event->credentials['username'] ?? ''), 0, 40));
    }
}
