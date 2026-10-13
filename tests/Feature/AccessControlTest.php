<?php

namespace Tests\Feature;

use App\Models\Order;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Routes;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Who may open what. The owner opens every module; the cashier, the kitchen and the stock clerk open only their
 * own; a visitor who isn't signed in opens none of them. Hiding sidebar links is only convenience: these tests
 * hit the addresses directly.
 */
class AccessControlTest extends TestCase
{
    /** One screen and one write of each module, as [GET address, [write method, address]]. */
    private const MODULES = [
        'cashier' => ['/cashier/orders', ['POST', '/cashier/orders/13/cancel']],
        'kitchen' => ['/kitchen/board', ['PATCH', '/kitchen/orders/10/status']],
        'inventory' => ['/inventory/ingredients', ['POST', '/inventory/ingredients/milk/restock']],
        'admin' => ['/admin/audit-logs', ['POST', '/admin/categories']],
    ];

    private const WHO = [
        'admin' => ['cashier', 'kitchen', 'inventory', 'admin'],
        'cashier' => ['cashier'],
        'kitchen' => ['kitchen'],
        'stock' => ['inventory'],
    ];

    #[DataProvider('accounts')]
    public function test_each_account_opens_its_own_modules_only(string $username, array $allowed): void
    {
        $this->signIn($username);

        foreach (self::MODULES as $module => [$screen, [$method, $write]]) {
            if (in_array($module, $allowed, true)) {
                $this->get($screen)->assertOk();
                // A write that is let in may still be refused for another reason (nothing was filled in), but never for who is asking
                $this->assertNotSame(403, $this->call($method, $write)->getStatusCode(), "{$username} should be let into {$module}");
            } else {
                $this->get($screen)->assertForbidden();
                $this->call($method, $write)->assertForbidden();
            }
        }
    }

    public static function accounts(): array
    {
        $accounts = [];
        foreach (self::WHO as $username => $allowed) {
            $accounts[$username] = [$username, $allowed];
        }

        return $accounts;
    }

    public function test_a_visitor_who_is_not_signed_in_is_sent_to_the_login_page(): void
    {
        foreach (self::MODULES as [$screen, [$method, $write]]) {
            $this->get($screen)->assertRedirect(route('login', ['next' => $screen]));
            $this->call($method, $write)->assertRedirect(route('login')); // a form that timed out: no "next" for a write
        }
        $this->getJson('/admin')->assertUnauthorized();
    }

    public function test_the_login_page_returns_to_the_screen_that_was_asked_for(): void
    {
        $this->get('/admin/audit-logs?area=Sales')->assertRedirect('/login?next=%2Fadmin%2Faudit-logs%3Farea%3DSales');

        $this->post(route('login.store'), ['username' => 'admin', 'password' => 'admin123', 'next' => '/admin/audit-logs?area=Sales'])
            ->assertRedirect('/admin/audit-logs?area=Sales');
    }

    public function test_every_staff_route_sits_behind_auth_and_its_modules_role(): void
    {
        $checked = 0;
        foreach (Routes::getRoutes() as $route) {
            /** @var Route $route */
            $module = strstr((string) $route->getName(), '.', true);
            if (! in_array($module, ['cashier', 'kitchen', 'inventory', 'admin'], true)) {
                continue;
            }

            $middleware = $route->gatherMiddleware();
            $this->assertContains('auth', $middleware, "{$route->getName()} has no login check");
            $this->assertContains("role:{$module}", $middleware, "{$route->getName()} has no role check");
            $checked++;
        }

        $this->assertGreaterThan(30, $checked);
    }

    public function test_the_only_routes_open_to_everyone_are_the_public_and_customer_ones(): void
    {
        $open = [];
        foreach (Routes::getRoutes() as $route) {
            if (! in_array('auth', $route->gatherMiddleware(), true)) {
                $open[] = $route->getName() ?: $route->uri();
            }
        }

        foreach ($open as $name) {
            $this->assertMatchesRegularExpression('/^(home|navigator|navigator\.components\.customer|login|login\.store|logout|customer\..+|up)$/', $name, "{$name} is open to visitors");
        }
        $this->assertContains('customer.menu', $open);
    }

    public function test_the_demo_navigator_components_page_is_for_the_owner(): void
    {
        $this->get(route('navigator.components.staff'))->assertRedirect();
        $this->signIn('cashier')->get(route('navigator.components.staff'))->assertForbidden();
        $this->asAdmin()->get(route('navigator.components.staff'))->assertOk();
    }

    public function test_a_customer_cannot_use_staff_actions_on_their_own_order(): void
    {
        // A customer holds the order's token, but the cashier's and kitchen's routes take order numbers and a staff login
        $token = Order::find(8)->public_token;

        $this->withSession(['my_orders' => [$token], 'qr' => ['type' => 'dine-in', 'table' => 1]]);
        $this->patch(route('kitchen.orders.status', 8), ['move' => 'ready'])->assertRedirect(route('login'));
        $this->post(route('cashier.orders.void', 8), ['reason' => 'Duplicate order'])->assertRedirect(route('login'));
        $this->get(route('cashier.receipt', 8))->assertRedirect(route('login', ['next' => '/cashier/orders/8/receipt']));
        $this->assertSame('preparing', Order::find(8)->status->value);
    }

    public function test_the_health_check_stays_open_for_monitoring(): void
    {
        $this->get('/up')->assertOk();
    }
}
