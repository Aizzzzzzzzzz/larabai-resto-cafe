<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\AuditLogController;
use App\Models\AuditLog;
use App\Services\AuditLogger;
use App\Support\AuditArea;
use Tests\TestCase;

/** Audit logs: filters, areas, CSV export, and what gets written. */
class AuditLogTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->asAdmin();
    }

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
        $this->assertStringContainsString('attachment; filename=larabai-audit-'.now()->format('Ymd').'.csv', $response->headers->get('content-disposition'));
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

    public function test_a_sign_in_a_failed_attempt_and_a_sign_out_are_logged(): void
    {
        auth()->logout();

        $this->post(route('login.store'), ['username' => 'kitchen', 'password' => 'not-the-password']);
        $failed = AuditLog::latest('id')->first();
        $this->assertSame(['Failed login', 'Username: kitchen', 'system', 'Login'], [$failed->action, $failed->detail, $failed->role, $failed->module]);
        $this->assertStringNotContainsString('not-the-password', $failed->detail);

        $this->post(route('login.store'), ['username' => 'kitchen', 'password' => 'kitchen123']);
        $in = AuditLog::latest('id')->first();
        $this->assertSame(['Logged in', 'Ramon Cruz', 'kitchen', 4], [$in->action, $in->user_name, $in->role, $in->user_id]);
        $this->assertNotNull(\App\Models\User::find(4)->last_login_at);
        $this->assertTrue(\App\Models\User::find(4)->last_login_at->isToday());

        $this->post(route('logout'));
        $this->assertSame('Logged out', AuditLog::latest('id')->first()->action);
    }

    public function test_writing_an_entry_files_it_under_its_area_and_the_filter_finds_it(): void
    {
        AuditLogger::record('Voided order', 'BB-TEST · ₱1.00 · Test', \App\Models\User::find(2));

        $row = AuditLog::latest('id')->first();
        $this->assertSame(['Needs review', 'cashier', 'Jessa Manalo'], [$row->module, $row->role, $row->user_name]);

        $this->get(route('admin.audit-logs.index', ['area' => 'Needs review', 'q' => 'BB-TEST']))->assertSee('BB-TEST')->assertSee('1 entry');
    }

    public function test_only_the_owner_reads_the_log(): void
    {
        foreach (['cashier', 'kitchen', 'stock'] as $username) {
            $this->signIn($username)->get(route('admin.audit-logs.index'))->assertForbidden();
            $this->get(route('admin.audit-logs.export'))->assertForbidden();
        }
    }

    public function test_actions_group_into_areas(): void
    {
        foreach ([
            'Voided order' => 'Needs review', 'Cancelled order' => 'Needs review', 'Applied discount' => 'Needs review',
            'Payment recorded' => 'Sales', 'Created counter order' => 'Sales',
            'Restocked' => 'Inventory', 'Stock count' => 'Inventory', 'Added ingredient' => 'Inventory',
            'Started preparing' => 'Kitchen', 'Marked sold out' => 'Kitchen', 'Completed order' => 'Kitchen',
            'Logged in' => 'Login', 'Logged out' => 'Login', 'Failed login' => 'Login', 'Moved back to new orders' => 'Kitchen',
            'Edited menu item' => 'Settings', 'Added user' => 'Settings',
        ] as $action => $area) {
            $this->assertSame($area, AuditArea::of($action)[0], $action);
        }
    }
}
