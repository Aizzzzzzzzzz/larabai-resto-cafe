<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/** Printable QR standees for the tables and the counter. */
class TableQrController extends Controller
{
    public function index(): View
    {
        return view('admin.table-qr');
    }
}
