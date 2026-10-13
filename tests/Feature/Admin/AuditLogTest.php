<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\AuditLogController;
use App\Support\AuditArea;
use Tests\TestCase;

/** UI scaffold Step 6 — Audit logs: filters, areas, CSV export. */
class AuditLogTest extends TestCase
{
    public function test_log_lists_entries_newest_first_with_area_chips(): void
    {
        $this->get(route('admin.audit-logs.index'))
            ->assertOk()
            ->assertSee('area-chip money', false)
            ->assertSee('area-chip risk', false)
            ->assertSee('area-chip stock', false)
            ->assertSee('Payment recorded')
            ->assertSee('Voided order')
            ->assertSee('Customer (QR)');
    }

    public function test_filters(): void
    {
        $this->get(route('admin.audit-logs.index', ['area' => 'Needs review']))
            ->assertSee('Voided order')->assertSee('Applied discount')->assertDontSee('Payment recorded');

        $this->get(route('admin.audit-logs.index', ['user' => 'Ramon Cruz']))
            ->assertSee('Marked ready')->assertDontSee('Payment recorded');

        $this->get(route('admin.audit-logs.index', ['q' => 'GC-']))->assertSee('Payment recorded')->assertDontSee('Marked ready');
        $this->get(route('admin.audit-logs.index', ['q' => 'nothing like this']))->assertSee('No entries match these filters.')->assertSee('0 entries');
    }

    public function test_period_filter(): void
    {
        $this->get(route('admin.audit-logs.index', ['range' => 'today']))->assertDontSee('Added user')->assertSee('Payment recorded');
        $this->get(route('admin.audit-logs.index', ['range' => 'all']))->assertSee('Added user');
        $this->get(route('admin.audit-logs.index'))->assertSee('<option value="7" selected>', false); // default: last 7 days
    }

    public function test_user_filter_lists_everyone_who_acted(): void
    {
        $this->get(route('admin.audit-logs.index'))
            ->assertSee('Jessa Manalo</option>', false)
            ->assertSee('Liza Garcia</option>', false);
    }

    public function test_export_follows_the_filters_and_opens_in_excel(): void
    {
        $response = $this->get(route('admin.audit-logs.export', ['area' => 'Needs review', 'range' => 'all']));
        $csv = $response->streamedContent();

        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment; filename=brew-bite-audit-'.now()->format('Ymd').'.csv', $response->headers->get('content-disposition'));
        $this->assertStringStartsWith("\xEF\xBB\xBFDate,User,Role,Area,Action,Details", $csv);
        $this->assertStringContainsString('Voided order', $csv);
        $this->assertStringNotContainsString('Payment recorded', $csv);
    }

    public function test_the_export_button_carries_the_current_filters(): void
    {
        $this->get(route('admin.audit-logs.index', ['area' => 'Sales', 'q' => 'GC-']))
            ->assertSee(e(route('admin.audit-logs.export', ['range' => '7', 'area' => 'Sales', 'q' => 'GC-'])), false);
    }

    public function test_spreadsheet_formulas_are_neutralised(): void
    {
        $this->assertSame("'=SUM(A1:A9)", AuditLogController::safeCell('=SUM(A1:A9)'));
        $this->assertSame("'@cmd", AuditLogController::safeCell('@cmd'));
        $this->assertSame("'-5", AuditLogController::safeCell('-5'));
        $this->assertSame('BB-0001 · CASH', AuditLogController::safeCell('BB-0001 · CASH'));
        $this->assertSame('', AuditLogController::safeCell(''));
    }

    public function test_actions_group_into_areas(): void
    {
        foreach ([
            'Voided order' => 'Needs review', 'Cancelled order' => 'Needs review', 'Applied discount' => 'Needs review',
            'Payment recorded' => 'Sales', 'Created counter order' => 'Sales',
            'Restocked' => 'Inventory', 'Stock count' => 'Inventory', 'Added ingredient' => 'Inventory',
            'Started preparing' => 'Kitchen', 'Marked sold out' => 'Kitchen', 'Completed order' => 'Kitchen',
            'Logged in' => 'Login', 'Edited menu item' => 'Settings', 'Added user' => 'Settings',
        ] as $action => $area) {
            $this->assertSame($area, AuditArea::of($action)[0], $action);
        }
    }
}
