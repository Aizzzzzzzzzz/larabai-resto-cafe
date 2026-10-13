<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\QrCodes;
use App\Support\CashierActions;
use App\Support\DemoData;
use App\Support\FloorPlan;
use App\Support\StockLevel;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Demo launch pad (demo mode only): live counts, a suggested walkthrough, the café floor plan where
 * opening a table is the same as scanning its QR, and every screen grouped by role with its demo login.
 */
class NavigatorController extends Controller
{
    public function index(): View
    {
        abort_unless(config('cafe.demo_mode'), 404);

        // UI scaffold: DemoData. Phase 1+ count the real rows.
        $orders = collect(DemoData::orders());
        $open = $orders->reject(fn ($o) => in_array($o['status'], ['completed', 'cancelled', 'voided'], true));
        $counts = [
            'unpaid' => $orders->filter(fn ($o) => CashierActions::unpaid($o))->count(),
            'kitchen' => $orders->whereIn('status', ['paid', 'preparing', 'ready'])->count(),
            'low' => collect(DemoData::ingredients())->filter(fn ($i) => StockLevel::of($i) !== StockLevel::OK)->count(),
        ];

        return view('public.navigator', [
            'live' => [
                ['n' => $orders->filter(fn ($o) => Carbon::parse($o['created_at'])->isToday())->count(), 'label' => 'orders today', 'attn' => false],
                ['n' => $counts['unpaid'], 'label' => 'awaiting payment', 'attn' => true],
                ['n' => $counts['kitchen'], 'label' => 'in the kitchen', 'attn' => false],
                ['n' => $counts['low'], 'label' => 'low on stock', 'attn' => true],
            ],
            'walk' => $this->walkthrough(),
            'spots' => $this->spots($open),
            'roles' => $this->roles($counts),
        ]);
    }

    /** One order from the table to the sales report. */
    private function walkthrough(): array
    {
        return [
            ['title' => 'Order as a customer', 'sub' => 'Table 5 · choose cash at the counter', 'href' => QrCodes::tableUrl(5)],
            ['title' => 'Take the payment', 'sub' => 'Cashier › Orders', 'login' => $this->login('cashier'), 'href' => route('cashier.orders.index')],
            ['title' => 'Prepare and serve it', 'sub' => 'Kitchen display', 'login' => $this->login('kitchen'), 'href' => route('kitchen.display')],
            ['title' => 'Check the stock it used', 'sub' => 'Inventory', 'login' => $this->login('inventory'), 'href' => route('inventory.ingredients.index')],
            ['title' => 'See it in the sales report', 'sub' => 'Admin dashboard', 'login' => $this->login('admin'), 'href' => route('admin.dashboard')],
        ];
    }

    /** The five tables, then the counter QR for take-out and delivery. Opening one scans its (signed) QR link. */
    private function spots($open): array
    {
        $spots = collect(DemoData::tables())->map(function ($t) use ($open) {
            $busy = $open->where('order_type', 'dine-in')->where('table_no', $t['table_no'])->count();
            $plan = FloorPlan::table($t['seats']);

            return [
                'href' => QrCodes::tableUrl($t['table_no']),
                'busy' => $busy > 0,
                'rider' => false,
                'svg' => $plan['svg'],
                'title' => 'Table '.str_pad((string) $t['table_no'], 2, '0', STR_PAD_LEFT),
                'sub' => $t['seats'].' seats',
                'state' => $busy ? $busy.' open '.str('order')->plural($busy) : 'Free',
                'aria' => "Table {$t['table_no']}, {$t['seats']} seats".($busy ? ", {$busy} open ".str('order')->plural($busy) : ', free'),
            ];
        });

        $takeout = $open->where('order_type', '!=', 'dine-in')->count();

        return $spots->push([
            'href' => QrCodes::counterUrl(),
            'busy' => $takeout > 0,
            'rider' => true,
            'svg' => FloorPlan::rider(),
            'title' => 'Take-out',
            'sub' => '& delivery',
            'state' => $takeout ? $takeout.' open '.str('order')->plural($takeout) : 'Counter QR',
            'aria' => 'Take-out and delivery'.($takeout ? ", {$takeout} open ".str('order')->plural($takeout) : ''),
        ])->all();
    }

    private function roles(array $counts): array
    {
        $screen = fn (string $name, string $url, ?string $flow = null) => ['name' => $name, 'path' => parse_url($url, PHP_URL_PATH), 'href' => $url, 'flow' => $flow];

        $roles = [
            ['title' => 'Public', 'who' => 'Anyone · phone or laptop', 'icon' => 'globe', 'screens' => [
                $screen('Café homepage', route('home')),
                $screen('Staff login', route('login')),
            ]],
            ['title' => 'Customer', 'who' => 'Own phone, after scanning a table QR', 'icon' => 'phone', 'screens' => [
                $screen('Menu', QrCodes::tableUrl(5)),
                $screen('Cart', route('customer.cart.index')),
                $screen('Checkout', route('customer.checkout.create')),
                $screen('GCash / Maya payment', route('customer.menu'), 'Opens from checkout'),
                $screen('Payment success and receipt', route('customer.menu'), 'Opens after paying'),
                $screen('My orders and tracker', route('customer.orders.index')),
            ]],
            ['title' => 'Cashier', 'role' => 'cashier', 'who' => 'Counter tablet or PC', 'icon' => 'till', 'badge' => $counts['unpaid'] ? ['chip-caramel', $counts['unpaid'].' unpaid'] : null, 'screens' => [
                $screen('Point of sale', route('cashier.pos.index')),
                $screen('Orders and payments', route('cashier.orders.index')),
                $screen('Take payment, senior/PWD discount', route('cashier.orders.index'), 'Opens from Orders'),
                $screen('Receipt', route('cashier.orders.index'), 'Opens after payment'),
            ]],
            ['title' => 'Kitchen', 'role' => 'kitchen', 'who' => 'Wall-mounted screen', 'icon' => 'cup', 'badge' => $counts['kitchen'] ? ['chip-green', $counts['kitchen'].' in queue'] : null, 'screens' => [
                $screen('Kitchen display and sold-out switches', route('kitchen.display')),
            ]],
            ['title' => 'Inventory', 'role' => 'inventory', 'who' => 'Stock clerk · back-office PC', 'icon' => 'box', 'badge' => $counts['low'] ? ['chip-caramel', $counts['low'].' low'] : null, 'screens' => [
                $screen('Stock, low-stock alerts and restocking', route('inventory.ingredients.index')),
            ]],
            ['title' => 'Admin', 'role' => 'admin', 'who' => 'Owner · laptop or phone', 'icon' => 'chart', 'screens' => [
                $screen('Sales dashboard', route('admin.dashboard')),
                $screen('Menu and prices', route('admin.menu-items.index')),
                $screen('Users and roles', route('admin.users.index')),
                $screen('Audit logs', route('admin.audit-logs.index')),
                $screen('Table QR codes (print)', route('admin.tables.qr')),
            ]],
        ];

        // Each staff card shows the demo login for its role
        return array_map(fn ($r) => $r + ['login' => isset($r['role']) ? $this->login($r['role']) : null], $roles);
    }

    /** Demo login for a role, e.g. "cashier / cashier123". */
    private function login(string $role): string
    {
        $user = collect(DemoData::users())->first(fn ($u) => $u['role'] === $role && $u['is_active']);

        return $user ? "{$user['username']} / {$user['password']}" : 'no active account';
    }
}
