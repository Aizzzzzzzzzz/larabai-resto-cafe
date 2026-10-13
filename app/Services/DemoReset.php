<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/** "Reset demo data" (document.md §3.7): counts what would be erased, then rebuilds the database from the seeders. */
class DemoReset
{
    /** @return array<string, int> what a reset erases, as the screen lists it */
    public function counts(): array
    {
        return [
            'orders' => DB::table('orders')->count(),
            'payments' => DB::table('payments')->count(),
            'Senior/PWD discount records' => DB::table('discounts')->count(),
            'stock movements' => DB::table('stock_movements')->count(),
            'audit log entries' => DB::table('audit_logs')->count(),
        ];
    }

    /** Drops every table and runs the migrations and seeders again, which restores the sample café. */
    public function run(): void
    {
        set_time_limit(300);

        Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);
    }
}
