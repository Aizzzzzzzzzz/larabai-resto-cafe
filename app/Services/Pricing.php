<?php

namespace App\Services;

/**
 * Money rules, in one place so every screen computes them the same way (document.md objective 5).
 * Pure functions on numbers — no database — so they are unit-tested directly.
 */
class Pricing
{
    /**
     * Senior Citizen / PWD discount on the eligible share of a shared order:
     * share = subtotal × eligible ÷ diners; remove 12% VAT from the share; take 20% off.
     * The discount is what the eligible share no longer costs.
     */
    public static function seniorDiscount(float $subtotal, int $eligible, int $diners): float
    {
        $share = self::eligibleShare($subtotal, $eligible, $diners);
        $discounted = $share / (1 + self::vatRate()) * (1 - config('cafe.senior_pwd.percent') / 100);

        return round($share - $discounted, 2);
    }

    public static function eligibleShare(float $subtotal, int $eligible, int $diners): float
    {
        return round($subtotal * min($eligible, $diners) / max(1, $diners), 2);
    }

    /** subtotal − discount + delivery fee, never below zero. */
    public static function total(float $subtotal, float $discount = 0, float $deliveryFee = 0): float
    {
        return max(0, round($subtotal - $discount + $deliveryFee, 2));
    }

    /**
     * Receipt VAT lines. Prices include 12% VAT; the delivery fee is outside it. With a Senior/PWD
     * discount the eligible share is VAT-exempt, so only the rest is VATable.
     *
     * @return array{vatable: float, vat: float, exempt: float}
     */
    public static function vatBreakdown(float $subtotal, float $total, float $deliveryFee, ?int $eligible = null, ?int $diners = null, float $discount = 0): array
    {
        $exempt = 0.0;
        $regular = $total - $deliveryFee;

        if ($eligible && $diners) {
            $share = self::eligibleShare($subtotal, $eligible, $diners);
            $exempt = round($share / (1 + self::vatRate()), 2);
            $regular = round($total - ($share - $discount) - $deliveryFee, 2);
        }

        $vatable = round($regular / (1 + self::vatRate()), 2);

        return ['vatable' => $vatable, 'vat' => round($regular - $vatable, 2), 'exempt' => $exempt];
    }

    /** Ready-made cash amounts for the payment screen: exact, next ₱50, next ₱100, ₱500, ₱1,000. */
    public static function quickCash(float $total): array
    {
        $amounts = [$total, ceil($total / 50) * 50, ceil($total / 100) * 100, 500, 1000];

        return array_values(array_unique(array_filter($amounts, fn ($a) => $a >= $total)));
    }

    private static function vatRate(): float
    {
        return (float) config('cafe.senior_pwd.vat');
    }
}
