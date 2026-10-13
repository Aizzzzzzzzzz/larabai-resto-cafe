<?php

use App\Http\Middleware\EnsureQrSession;
use App\Http\Middleware\EnsureRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'qr.session' => EnsureQrSession::class,
            'role' => EnsureRole::class,
        ]);

        // A visitor without a staff login is sent to the login page, which brings them back afterwards.
        // Only a page they asked for (GET) is remembered; a form that timed out just goes to the login.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->isMethod('GET')
            ? route('login', ['next' => $request->getRequestUri()])
            : route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
