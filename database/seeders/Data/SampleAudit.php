<?php

namespace Database\Seeders\Data;

use App\Support\Format;
use Illuminate\Support\Carbon;

/**
 * A sample audit log (document.md §4.17): today's sample orders explain most entries (counter orders,
 * payments, discounts, voids, kitchen moves), plus logins, a restock and a few settings changes
 * from earlier in the week.
 *
 * Seed data only — the running app writes real rows through AuditLogger.
 */
class SampleAudit
{
    private const ROLES = ['admin' => 'admin', 'cashier' => 'cashier', 'kitchen' => 'kitchen', 'inventory' => 'inventory'];

    /** @return array<int, array{logged_at: string, user: string, role: string, action: string, detail: string}> oldest first */
    public static function all(): array
    {
        $rows = [];
        $add = function (Carbon|string $at, string $user, string $role, string $action, string $detail) use (&$rows) {
            $rows[] = ['logged_at' => Carbon::parse($at)->toDateTimeString(), 'user' => $user, 'role' => $role, 'action' => $action, 'detail' => $detail];
        };

        $first = collect(SampleData::orders())->min('created_at');
        $open = Carbon::parse($first)->subHours(2);
        $users = collect(SampleData::users())->keyBy('username');
        foreach (['cashier' => 12, 'kitchen' => 8, 'stock' => 5] as $username => $minutes) {
            $u = $users[$username];
            $add($open->copy()->addMinutes($minutes), $u['full_name'], $u['role'], 'Logged in', "{$u['full_name']} ({$u['role']})");
        }
        $add($open->copy()->addMinutes(20), 'Liza Garcia', 'inventory', 'Restocked', 'Fresh milk · +10 L · Delivered by Central Luzon Dairy');

        foreach (SampleData::orders() as $o) {
            $no = $o['order_no'];
            if ($o['source'] === 'pos') {
                $add($o['created_at'], 'Jessa Manalo', 'cashier', 'Created counter order', "{$no} · ".Format::peso($o['total']));
            }
            if ($d = $o['discount']) {
                $add($o['paid_at'], 'Jessa Manalo', 'cashier', 'Applied discount', "{$no} · {$d['discount_type']} {$d['id_no']} · −".Format::peso($d['amount']));
            }
            if ($p = $o['payment']) {
                $who = $p['received_by'] ?? 'Customer (QR)';
                $add($p['paid_at'], $who, $p['received_by'] ? 'cashier' : 'customer', 'Payment recorded', "{$no} · {$p['method']} · ".Format::peso($p['amount'])." · {$p['reference_no']}");
                if ($p['status'] === 'VOIDED') {
                    $add($p['voided_at'], 'Jessa Manalo', 'cashier', 'Voided order', "{$no} · ".Format::peso($o['total'])." · {$o['cancel_reason']}");
                }
            }
            if ($o['status'] === 'cancelled') {
                $add(collect($o['status_log'])->last()['at'], 'Customer (QR)', 'customer', 'Cancelled order', "{$no} · {$o['cancel_reason']}");
            }
            foreach ($o['status_log'] as $log) {
                $detail = match ($log['status']) {
                    'preparing' => ['Started preparing', $no],
                    'ready' => ['Marked ready', $no],
                    'completed' => ['Completed order', "{$no} · {$o['order_type']}"],
                    default => null,
                };
                if ($detail) {
                    $kitchen = in_array($log['status'], ['preparing', 'ready'], true);
                    $add($log['at'], $kitchen ? 'Ramon Cruz' : 'Jessa Manalo', $kitchen ? 'kitchen' : 'cashier', $detail[0], $detail[1]);
                }
            }
        }

        // Earlier in the week: the owner's settings changes, and a sold-out switch
        $owner = ['Maria Dizon', 'admin'];
        $add(now()->subDays(2)->setTime(9, 12), ...[...$owner, 'Edited menu item', 'Café Latte · price ₱140.00 → ₱145.00']);
        $add(now()->subDays(2)->setTime(9, 20), ...[...$owner, 'Added menu item', 'Sea Salt Latte · ₱170.00']);
        $add(now()->subDays(3)->setTime(16, 5), 'Ramon Cruz', 'kitchen', 'Marked sold out', 'Cookies & Cream Frappe');
        $add(now()->subDays(3)->setTime(18, 40), 'Ramon Cruz', 'kitchen', 'Marked available', 'Cookies & Cream Frappe');
        $add(now()->subDays(5)->setTime(8, 30), ...[...$owner, 'Added user', 'Mark Bautista · Cashier']);
        $add(now()->subDays(5)->setTime(8, 31), ...[...$owner, 'Edited user', 'Jessa Manalo · password reset']);

        usort($rows, fn ($a, $b) => $a['logged_at'] <=> $b['logged_at']);

        return $rows;
    }
}
