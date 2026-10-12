<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Customer screens after the menu need a scanned table or counter QR (session "qr").
 * Without one, send the visitor to the menu, which shows "Scan the QR code on your table".
 */
class EnsureQrSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->has('qr')) {
            return redirect()->route('customer.menu');
        }

        return $next($request);
    }
}
