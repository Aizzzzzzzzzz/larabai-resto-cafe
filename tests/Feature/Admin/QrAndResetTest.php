<?php

namespace Tests\Feature\Admin;

use App\Models\Order;
use App\Services\DemoReset;
use App\Services\QrCodes;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\URL;
use Mockery\MockInterface;
use Tests\TestCase;

/** Printable QR sheet and the demo-data reset. */
class QrAndResetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->asAdmin();
    }

    public function test_qr_sheet_has_a_code_for_every_table_and_the_counter(): void
    {
        $html = $this->get(route('admin.tables.qr'))->assertOk()->assertSee('data-print', false)->getContent();

        foreach (['Table 1', 'Table 2', 'Table 3', 'Table 4', 'Table 5', 'Counter'] as $title) {
            $this->assertStringContainsString("QR code for {$title}", $html);
        }
        $this->assertSame(6, substr_count($html, 'class="qr-card"'));
        $this->assertSame(6, substr_count($html, 'class="qr-svg qrcode"'));
        $this->assertStringNotContainsString('<?xml', $html);
        $this->get(route('admin.tables.qr'))->assertSee('2 seats · dine-in')->assertSee('4 seats · dine-in')->assertSee('6 seats · dine-in');
    }

    public function test_qr_links_are_signed_and_open_the_right_session(): void
    {
        $url = QrCodes::tableUrl(4);

        $this->assertTrue(URL::hasValidSignature(request()->create($url)));
        $this->get($url)->assertRedirect(route('customer.menu'))->assertSessionHas('qr', ['type' => 'dine-in', 'table' => 4]);
        $this->get(QrCodes::counterUrl())->assertSessionHas('qr', ['type' => 'counter', 'table' => null]);
    }

    public function test_qr_svg_is_inline_markup(): void
    {
        $svg = QrCodes::svg('https://example.test/t/3');

        $this->assertStringStartsWith('<svg', $svg);
        $this->assertStringContainsString('qr-darkmodule', $svg);
    }

    public function test_reset_page_previews_what_will_be_erased(): void
    {
        $this->get(route('admin.demo-reset.show'))
            ->assertOk()
            ->assertSee('This will erase')
            ->assertSeeInOrder(['13', 'orders', '8', 'payments', '1', 'Senior/PWD discount records', 'stock movements', 'audit log entries'])
            ->assertSee('Type RESET to confirm')
            ->assertSee('data-confirm-word="RESET"', false);
    }

    public function test_reset_needs_the_word_checked_on_the_server(): void
    {
        $this->mock(DemoReset::class, fn (MockInterface $m) => $m->shouldReceive('run')->never());

        $this->post(route('admin.demo-reset.store'), ['confirm' => 'reset'])->assertSessionHasErrors(['confirm' => 'Type RESET exactly to continue.']);
        $this->post(route('admin.demo-reset.store'))->assertSessionHasErrors('confirm');
    }

    public function test_reset_runs_and_signs_the_owner_out(): void
    {
        $this->mock(DemoReset::class, fn (MockInterface $m) => $m->shouldReceive('run')->once());

        $this->post(route('admin.demo-reset.store'), ['confirm' => 'RESET'])
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Demo data restored. Sign in again.');
        $this->assertGuest();
    }

    public function test_reset_preview_counts_the_real_rows(): void
    {
        Order::where('id', '>', 8)->delete();

        $this->assertSame(8, app(DemoReset::class)->counts()['orders']);
        $this->get(route('admin.demo-reset.show'))->assertSeeInOrder(['8', 'orders']);
    }

    public function test_reset_rebuilds_the_database_with_the_seeders(): void
    {
        // The real thing wipes the database, so it is run by hand (php artisan migrate:fresh --seed); here we check what the button calls
        Artisan::shouldReceive('call')->once()->with('migrate:fresh', ['--seed' => true, '--force' => true]);

        app(DemoReset::class)->run();
    }

    public function test_reset_exists_only_in_demo_mode(): void
    {
        config(['cafe.demo_mode' => false]);

        $this->get(route('admin.demo-reset.show'))->assertNotFound();
        $this->post(route('admin.demo-reset.store'), ['confirm' => 'RESET'])->assertNotFound();
        $this->get(route('admin.dashboard'))->assertDontSee('Reset demo data');
    }

    public function test_only_the_owner_can_reset(): void
    {
        $this->signIn('cashier')->get(route('admin.demo-reset.show'))->assertForbidden();
        $this->post(route('admin.demo-reset.store'), ['confirm' => 'RESET'])->assertForbidden();
    }
}
