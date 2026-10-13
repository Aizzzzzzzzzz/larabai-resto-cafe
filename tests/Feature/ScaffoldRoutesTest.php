<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Shell checks left from the UI scaffold (ui-scaffold-checklist.md): the shared-components gallery,
 * signed QR links and the mockup's layout classes. Every module has its own feature tests
 * (tests/Feature/Customer, Cashier, Kitchen, Inventory, Admin, Public).
 */
class ScaffoldRoutesTest extends TestCase
{
    public static function screens(): array
    {
        return [
            'components (staff)' => ['navigator.components.staff'],
            'components (customer)' => ['navigator.components.customer'],
        ];
    }

    #[DataProvider('screens')]
    public function test_screen_opens(string $route, array $parameters = []): void
    {
        $this->get(route($route, $parameters))->assertOk();
    }

    public function test_table_qr_starts_a_dine_in_session(): void
    {
        $this->get(URL::signedRoute('customer.session.table', ['table' => 3]))
            ->assertRedirect(route('customer.menu'))
            ->assertSessionHas('qr', ['type' => 'dine-in', 'table' => 3]);
    }

    public function test_counter_qr_starts_a_counter_session(): void
    {
        $this->get(URL::signedRoute('customer.session.counter'))
            ->assertRedirect(route('customer.menu'))
            ->assertSessionHas('qr', ['type' => 'counter', 'table' => null]);
    }

    public function test_qr_links_cannot_be_edited(): void
    {
        $url = URL::signedRoute('customer.session.table', ['table' => 3]);

        $this->get(str_replace('/t/3', '/t/4', $url))->assertForbidden();
        $this->get('/t/3')->assertForbidden();
    }

    public function test_staff_screens_render_the_mockup_shell(): void
    {
        $this->get(route('cashier.pos.index'))
            ->assertSee('class="m-staff staff v-cashier-pos"', false)
            ->assertSee('Point of sale')
            ->assertSee('Kitchen display')
            ->assertSee('Maria Dizon');
    }

    public function test_flash_message_shows_as_toast(): void
    {
        $this->withSession(['status' => 'Saved!'])
            ->get(route('cashier.orders.index'))
            ->assertSeeInOrder(['class="toast-pop"', 'Saved!'], false);
    }
}
