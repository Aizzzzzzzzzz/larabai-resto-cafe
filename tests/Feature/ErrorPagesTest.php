<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Scaffold Step 8 — friendly error pages in the café's look, with no stack traces and no session needed. */
class ErrorPagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.debug' => false]); // with debug on, Laravel shows its developer error page instead

        Route::middleware('web')->group(function () {
            Route::get('/_boom', fn () => throw new \RuntimeException('secret database password hunter2'));
            Route::get('/_forbidden', fn () => abort(403));
            Route::get('/_expired', fn () => abort(419));
            Route::get('/_busy', fn () => abort(429));
            Route::get('/_maintenance', fn () => abort(503));
        });
    }

    public function test_a_missing_page_says_so_kindly(): void
    {
        $this->get('/no/such/page')
            ->assertNotFound()
            ->assertSee('We can’t find that page')
            ->assertSee('scan the QR code on your table')
            ->assertSee('href="'.url('/').'"', false)
            ->assertSee('class="v-error-page"', false)
            ->assertSee('favicon.svg', false);
    }

    public function test_each_error_has_its_own_plain_message(): void
    {
        $this->get('/_forbidden')->assertForbidden()->assertSee('You can’t open this page');
        $this->get('/_expired')->assertStatus(419)->assertSee('This page expired')->assertSee('Nothing was saved or charged');
        $this->get('/_busy')->assertStatus(429)->assertSee('Please wait a moment');
        $this->get('/_maintenance')->assertStatus(503)->assertSee('We’ll be back in a moment');
    }

    public function test_a_server_error_never_shows_details(): void
    {
        $this->get('/_boom')
            ->assertStatus(500)
            ->assertSee('Something went wrong on our side')
            ->assertDontSee('hunter2')
            ->assertDontSee('RuntimeException')
            ->assertDontSee('Stack trace');
    }

    public function test_the_expired_page_offers_a_way_back_only_inside_this_site(): void
    {
        $this->get('/_expired', ['Referer' => url('/cashier/pos')])->assertSee('href="'.url('/cashier/pos').'"', false);

        // A look-alike host that merely starts with this site's address is not "back"
        $this->get('/_expired', ['Referer' => url('/').'.evil.example/x'])->assertDontSee('evil.example')->assertSee('href="'.url('/').'"', false);
        $this->get('/_expired', ['Referer' => 'https://evil.example/'])->assertDontSee('evil.example');
    }

    public function test_error_pages_do_not_need_the_session(): void
    {
        $page = view('errors.404')->render();

        $this->assertStringContainsString('We can’t find that page', $page);
        $this->assertStringNotContainsString('csrf-token', $page);
    }
}
