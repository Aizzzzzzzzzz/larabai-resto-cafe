<?php

namespace App\Providers;

use App\Listeners\LogAuthActivity;
use App\View\Composers\CustomerComposer;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        View::composer(['customer.*', 'components.customer.*'], CustomerComposer::class);

        // Sign-ins, sign-outs and failed attempts feed the audit log
        Event::listen(Login::class, [LogAuthActivity::class, 'onLogin']);
        Event::listen(Logout::class, [LogAuthActivity::class, 'onLogout']);
        Event::listen(Failed::class, [LogAuthActivity::class, 'onFailed']);
    }
}
