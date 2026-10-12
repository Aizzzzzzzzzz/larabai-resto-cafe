<?php

namespace Tests\Feature;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * UI scaffold check (ui-scaffold-checklist.md): every screen opens, every form route answers
 * with the "nothing is saved" toast. Replaced module by module by real feature tests.
 */
class ScaffoldRoutesTest extends TestCase
{
    public static function screens(): array
    {
        return [
            'home' => ['home'],
            'navigator' => ['navigator'],
            'login' => ['login'],
            'customer menu' => ['customer.menu'],
            'customer cart' => ['customer.cart.index'],
            'customer checkout' => ['customer.checkout.create'],
            'customer my orders' => ['customer.orders.index'],
            'customer pay' => ['customer.pay.create', ['order' => 'demo']],
            'customer success' => ['customer.orders.success', ['order' => 'demo']],
            'customer track' => ['customer.orders.show', ['order' => 'demo']],
            'customer status poll' => ['customer.orders.status', ['order' => 'demo']],
            'cashier pos' => ['cashier.pos.index'],
            'cashier orders' => ['cashier.orders.index'],
            'cashier payment' => ['cashier.payments.create', ['order' => 'demo']],
            'cashier receipt' => ['cashier.receipt', ['order' => 'demo']],
            'kitchen display' => ['kitchen.display'],
            'kitchen board poll' => ['kitchen.board'],
            'inventory stock' => ['inventory.ingredients.index'],
            'inventory history' => ['inventory.ingredients.history', ['ingredient' => 'milk']],
            'admin dashboard' => ['admin.dashboard'],
            'admin menu' => ['admin.menu-items.index'],
            'admin users' => ['admin.users.index'],
            'admin audit logs' => ['admin.audit-logs.index'],
            'admin audit export' => ['admin.audit-logs.export'],
            'admin demo reset' => ['admin.demo-reset.show'],
            'admin table qr' => ['admin.tables.qr'],
        ];
    }

    public static function writes(): array
    {
        return [
            'login' => ['post', 'login.store'],
            'logout' => ['post', 'logout'],
            'cart add' => ['post', 'customer.cart.store'],
            'cart update' => ['patch', 'customer.cart.update', ['line' => 1]],
            'cart remove' => ['delete', 'customer.cart.destroy', ['line' => 1]],
            'checkout' => ['post', 'customer.checkout.store'],
            'customer pay' => ['post', 'customer.pay.store', ['order' => 'demo']],
            'customer cancel' => ['post', 'customer.orders.cancel', ['order' => 'demo']],
            'pos charge' => ['post', 'cashier.pos.store'],
            'order complete' => ['patch', 'cashier.orders.complete', ['order' => 'demo']],
            'order cancel' => ['post', 'cashier.orders.cancel', ['order' => 'demo']],
            'order void' => ['post', 'cashier.orders.void', ['order' => 'demo']],
            'cashier payment' => ['post', 'cashier.payments.store', ['order' => 'demo']],
            'kitchen status' => ['patch', 'kitchen.orders.status', ['order' => 'demo']],
            'kitchen sold out' => ['patch', 'kitchen.availability.update', ['menuItem' => 'latte']],
            'ingredient add' => ['post', 'inventory.ingredients.store'],
            'ingredient edit' => ['put', 'inventory.ingredients.update', ['ingredient' => 'milk']],
            'restock' => ['post', 'inventory.ingredients.restock', ['ingredient' => 'milk']],
            'menu item add' => ['post', 'admin.menu-items.store'],
            'menu item edit' => ['put', 'admin.menu-items.update', ['menu_item' => 'latte']],
            'menu item remove' => ['delete', 'admin.menu-items.destroy', ['menu_item' => 'latte']],
            'category add' => ['post', 'admin.categories.store'],
            'category edit' => ['put', 'admin.categories.update', ['category' => 'coffee']],
            'category remove' => ['delete', 'admin.categories.destroy', ['category' => 'coffee']],
            'user add' => ['post', 'admin.users.store'],
            'user edit' => ['put', 'admin.users.update', ['user' => 1]],
            'user password' => ['patch', 'admin.users.password', ['user' => 1]],
            'user deactivate' => ['patch', 'admin.users.active', ['user' => 1]],
            'demo reset' => ['post', 'admin.demo-reset.store'],
        ];
    }

    #[DataProvider('screens')]
    public function test_screen_opens(string $route, array $parameters = []): void
    {
        $this->get(route($route, $parameters))->assertOk();
    }

    #[DataProvider('writes')]
    public function test_form_route_answers_without_saving(string $method, string $route, array $parameters = []): void
    {
        $this->{$method}(route($route, $parameters))
            ->assertRedirect()
            ->assertSessionHas('status', Controller::DEMO_MESSAGE);
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
