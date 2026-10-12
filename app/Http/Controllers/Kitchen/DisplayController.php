<?php

namespace App\Http\Controllers\Kitchen;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class DisplayController extends Controller
{
    public function index(): View
    {
        return view('kitchen.display');
    }

    /** The board's columns only, re-fetched by the display every few seconds. */
    public function board(): View
    {
        return view('kitchen.partials.board');
    }
}
