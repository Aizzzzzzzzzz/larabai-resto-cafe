<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Catalog;
use App\Services\Orders;
use Illuminate\View\View;

/**
 * Demo-only preview of the shared components on sample data, one page per side
 * (the mockup's CSS styles staff and customer screens separately).
 */
class ComponentGalleryController extends Controller
{
    public function staff(): View
    {
        abort_unless(config('cafe.demo_mode'), 404);

        return view('gallery.staff', ['orders' => Orders::today()]);
    }

    public function customer(): View
    {
        abort_unless(config('cafe.demo_mode'), 404);

        return view('gallery.customer', [
            'orders' => Orders::today(),
            'drink' => Catalog::item('latte'),
            'meal' => Catalog::item('tapa'),
        ]);
    }
}
