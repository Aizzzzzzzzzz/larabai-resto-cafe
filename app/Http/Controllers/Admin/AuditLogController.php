<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function index(): View
    {
        return view('admin.audit-logs');
    }

    /** CSV of the log with the list's current filters. */
    public function export(): StreamedResponse
    {
        return response()->streamDownload(function () {
            echo "Date and time,User,Role,Module,Action,Details\n";
        }, 'audit-log.csv', ['Content-Type' => 'text/csv']);
    }
}
