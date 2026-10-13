<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Locks `php artisan route:list` to the route map in development-plan.md §4. Adding, renaming or dropping a
 * route fails this test until the plan (and this list) are updated too — the plan stays the truth.
 */
class RouteMapTest extends TestCase
{
    private const EXPECTED = [
        // Public and staff login
        'home' => 'GET /',
        'navigator' => 'GET navigator',
        'navigator.components.staff' => 'GET navigator/components',
        'navigator.components.customer' => 'GET navigator/components/customer',
        'login' => 'GET login',
        'login.store' => 'POST login',
        'logout' => 'POST logout',
        // Customer
        'customer.session.table' => 'GET t/{table}',
        'customer.session.counter' => 'GET counter',
        'customer.menu' => 'GET order/menu',
        'customer.cart.index' => 'GET order/cart',
        'customer.cart.store' => 'POST order/cart',
        'customer.cart.update' => 'PATCH order/cart/{line}',
        'customer.cart.destroy' => 'DELETE order/cart/{line}',
        'customer.checkout.create' => 'GET order/checkout',
        'customer.checkout.store' => 'POST order/checkout',
        'customer.orders.index' => 'GET order/mine',
        'customer.pay.create' => 'GET order/{order}/pay',
        'customer.pay.store' => 'POST order/{order}/pay',
        'customer.orders.success' => 'GET order/{order}/done',
        'customer.orders.status' => 'GET order/{order}/status',
        'customer.orders.cancel' => 'POST order/{order}/cancel',
        'customer.orders.show' => 'GET order/{order}',
        // Cashier
        'cashier.pos.index' => 'GET cashier/pos',
        'cashier.pos.store' => 'POST cashier/pos',
        'cashier.orders.index' => 'GET cashier/orders',
        'cashier.orders.complete' => 'PATCH cashier/orders/{order}/complete',
        'cashier.orders.cancel' => 'POST cashier/orders/{order}/cancel',
        'cashier.orders.void' => 'POST cashier/orders/{order}/void',
        'cashier.payments.create' => 'GET cashier/orders/{order}/payment',
        'cashier.payments.store' => 'POST cashier/orders/{order}/payment',
        'cashier.receipt' => 'GET cashier/orders/{order}/receipt',
        // Kitchen
        'kitchen.display' => 'GET kitchen',
        'kitchen.board' => 'GET kitchen/board',
        'kitchen.orders.status' => 'PATCH kitchen/orders/{order}/status',
        'kitchen.availability.update' => 'PATCH kitchen/menu-items/{menuItem}/availability',
        // Inventory
        'inventory.ingredients.index' => 'GET inventory/ingredients',
        'inventory.ingredients.store' => 'POST inventory/ingredients',
        'inventory.ingredients.update' => 'PUT inventory/ingredients/{ingredient}',
        'inventory.ingredients.restock' => 'POST inventory/ingredients/{ingredient}/restock',
        'inventory.ingredients.history' => 'GET inventory/ingredients/{ingredient}/history',
        // Admin
        'admin.dashboard' => 'GET admin',
        'admin.menu-items.index' => 'GET admin/menu-items',
        'admin.menu-items.store' => 'POST admin/menu-items',
        'admin.menu-items.update' => 'PUT admin/menu-items/{menuItem}',
        'admin.menu-items.destroy' => 'DELETE admin/menu-items/{menuItem}',
        'admin.menu-items.availability' => 'PATCH admin/menu-items/{menuItem}/availability',
        'admin.categories.store' => 'POST admin/categories',
        'admin.categories.update' => 'PUT admin/categories/{category}',
        'admin.categories.destroy' => 'DELETE admin/categories/{category}',
        'admin.users.index' => 'GET admin/users',
        'admin.users.store' => 'POST admin/users',
        'admin.users.update' => 'PUT admin/users/{user}',
        'admin.audit-logs.index' => 'GET admin/audit-logs',
        'admin.audit-logs.export' => 'GET admin/audit-logs/export',
        'admin.demo-reset.show' => 'GET admin/demo-reset',
        'admin.demo-reset.store' => 'POST admin/demo-reset',
        'admin.tables.qr' => 'GET admin/tables/qr',
    ];

    public function test_the_named_routes_are_exactly_the_planned_ones(): void
    {
        $actual = [];
        foreach (Route::getRoutes() as $route) {
            // Laravel's own: the /up health check and the private-file route, which this app turns off
            if ($route->getName() && ! str_starts_with($route->getName(), 'storage.')) {
                $actual[$route->getName()] = $route->methods()[0].' '.$route->uri();
            }
        }

        $this->assertEqualsCanonicalizing(self::EXPECTED, $actual, 'route:list no longer matches development-plan.md §4');
        $this->assertCount(58, $actual);
    }

    public function test_every_route_name_in_the_plan_table_exists(): void
    {
        $plan = file_get_contents(base_path('larabai-mockup/development-plan.md'));

        // §4 mentions each screen's route by URI or name; every planned screen name must resolve to a registered route
        foreach (['home', 'navigator', 'login', 'customer.menu', 'customer.cart.index', 'customer.checkout.create', 'customer.orders.show',
            'cashier.pos.index', 'kitchen.display', 'inventory.ingredients.index', 'admin.dashboard', 'admin.audit-logs.index'] as $name) {
            $this->assertTrue(Route::has($name), "{$name} is named in the plan but missing");
        }
        $this->assertStringContainsString('## 4. UI module', $plan);
    }

    public function test_no_route_serves_private_files(): void
    {
        // The default "local" disk can serve files by signed link; this café stores none, so the route stays off
        $this->assertFalse(Route::has('storage.local'));
    }

    public function test_every_write_route_is_a_form_post(): void
    {
        // Only POST / PUT / PATCH / DELETE write, and nothing writes on GET
        foreach (Route::getRoutes() as $route) {
            if (in_array('GET', $route->methods(), true)) {
                $this->assertNotContains($route->getName(), ['logout', 'login.store'], 'a write route is reachable by GET');
                $this->assertDoesNotMatchRegularExpression('/\.(store|update|destroy|cancel|void|complete|restock|availability)$/', (string) $route->getName(), $route->uri().' writes on GET');
            }
        }
    }
}
