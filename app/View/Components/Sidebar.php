<?php

namespace App\View\Components;

use App\Enums\Role;
use App\Support\CurrentStaff;
use App\Support\DemoData;
use App\Support\StockLevel;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Staff sidebar — the mockup's NAV list, filtered by the viewer's role (admin sees its own group first).
 *
 * UI scaffold: the viewer comes from the demo login (session 'demo_staff'), defaulting to the owner.
 * Phase 0 replaces this with auth()->user(). Hiding links is only convenience — routes are
 * protected by middleware and policies, never by this list.
 */
class Sidebar extends Component
{
    public const NAV = [
        ['group' => 'Cashier', 'roles' => [Role::Cashier, Role::Admin], 'links' => [
            ['id' => 'pos', 'label' => 'Point of sale', 'route' => 'cashier.pos.index'],
            ['id' => 'orders', 'label' => 'Orders', 'route' => 'cashier.orders.index', 'badge' => 'awaiting'],
        ]],
        ['group' => 'Kitchen', 'roles' => [Role::Kitchen, Role::Admin], 'links' => [
            ['id' => 'kitchen', 'label' => 'Kitchen display', 'route' => 'kitchen.display'],
        ]],
        ['group' => 'Inventory', 'roles' => [Role::Inventory, Role::Admin], 'links' => [
            ['id' => 'ingredients', 'label' => 'Stock', 'route' => 'inventory.ingredients.index', 'badge' => 'lowstock'],
        ]],
        ['group' => 'Admin', 'roles' => [Role::Admin], 'links' => [
            ['id' => 'dashboard', 'label' => 'Dashboard', 'route' => 'admin.dashboard'],
            ['id' => 'menu', 'label' => 'Menu & prices', 'route' => 'admin.menu-items.index'],
            ['id' => 'users', 'label' => 'Users & roles', 'route' => 'admin.users.index'],
            ['id' => 'audit', 'label' => 'Audit logs', 'route' => 'admin.audit-logs.index'],
        ]],
    ];

    public array $groups;

    public array $user;

    public Role $role;

    /**
     * @param  string  $active  id of the highlighted link
     * @param  array<string, int>|null  $badges  e.g. ['awaiting' => 2, 'lowstock' => 5]; 0 hides the badge.
     *                                           Left out, the live counts are used.
     */
    public function __construct(public string $active = '', public ?array $badges = null)
    {
        $this->user = CurrentStaff::get();
        $this->role = CurrentStaff::role();
        $this->badges ??= self::liveBadges();

        $groups = array_filter(self::NAV, fn ($g) => in_array($this->role, $g['roles'], true));
        usort($groups, fn ($a, $b) => ($b['group'] === 'Admin') <=> ($a['group'] === 'Admin'));
        $this->groups = $groups;
    }

    /**
     * Orders waiting for payment, and ingredients that are low or out.
     * UI scaffold: counted from DemoData; Phase 4 / 6 count from the database.
     */
    public static function liveBadges(): array
    {
        return [
            'awaiting' => collect(DemoData::orders())->filter(fn ($o) => ! $o['paid_at'] && ! in_array($o['status'], ['cancelled', 'voided'], true))->count(),
            'lowstock' => collect(DemoData::ingredients())->filter(fn ($i) => StockLevel::of($i) !== StockLevel::OK)->count(),
        ];
    }

    public function render(): View
    {
        return view('components.sidebar');
    }
}
