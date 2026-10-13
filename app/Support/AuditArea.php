<?php

namespace App\Support;

/**
 * Groups audit log actions into areas so the owner can filter, e.g. everything about money
 * (mockup admin/audit area()). AuditLogger files each entry under its area when it is written.
 */
class AuditArea
{
    public const NAMES = ['Needs review', 'Sales', 'Kitchen', 'Inventory', 'Settings', 'Login'];

    /** @return array{0: string, 1: string} area name and its chip class */
    public static function of(string $action): array
    {
        $a = mb_strtolower($action);

        return match (true) {
            (bool) preg_match('/void|cancel|discount/', $a) => ['Needs review', 'risk'],
            (bool) preg_match('/payment|counter order/', $a) => ['Sales', 'money'],
            (bool) preg_match('/stock|restock|ingredient/', $a) => ['Inventory', 'stock'],
            (bool) preg_match('/preparing|ready|completed|recalled|moved back|sold out|available/', $a) => ['Kitchen', ''],
            (bool) preg_match('/logged|login/', $a) => ['Login', ''],
            default => ['Settings', 'admin'],
        };
    }
}
