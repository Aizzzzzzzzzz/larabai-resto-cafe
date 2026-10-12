<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class NavigatorController extends Controller
{
    /** Demo launch pad that opens every screen (demo mode only). */
    public function index(): View
    {
        abort_unless(config('cafe.demo_mode'), 404);

        return view('public.navigator');
    }
}
