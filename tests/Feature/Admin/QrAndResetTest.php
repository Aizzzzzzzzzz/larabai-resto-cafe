<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Controller;
use App\Services\QrCodes;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/** UI scaffold Step 6 — printable QR sheet and the demo-data reset. */
class QrAndResetTest extends TestCase
{
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
        $this->post(route('admin.demo-reset.store'), ['confirm' => 'reset'])->assertSessionHasErrors(['confirm' => 'Type RESET exactly to continue.']);
        $this->post(route('admin.demo-reset.store'))->assertSessionHasErrors('confirm');
        $this->post(route('admin.demo-reset.store'), ['confirm' => 'RESET'])->assertRedirect(route('admin.dashboard'))->assertSessionHas('status', Controller::DEMO_MESSAGE);
    }

    public function test_reset_exists_only_in_demo_mode(): void
    {
        config(['cafe.demo_mode' => false]);

        $this->get(route('admin.demo-reset.show'))->assertNotFound();
        $this->post(route('admin.demo-reset.store'), ['confirm' => 'RESET'])->assertNotFound();
        $this->get(route('admin.dashboard'))->assertDontSee('Reset demo data');
    }
}
