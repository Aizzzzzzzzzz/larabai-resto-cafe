<?php

namespace Tests\Unit;

use App\Services\Pricing;
use Tests\TestCase;

/** Money rules (document.md objective 5 and the receipt's VAT lines). */
class PricingTest extends TestCase
{
    public function test_senior_discount_applies_only_to_the_eligible_share(): void
    {
        // ₱510 shared by 2, 1 senior: share ₱255 → ÷1.12 = ₱227.68 → less 20% = ₱182.14 → discount ₱72.86
        $this->assertSame(72.86, Pricing::seniorDiscount(510, 1, 2));
        // Senior eating alone: the whole bill is the share
        $this->assertSame(Pricing::seniorDiscount(255, 1, 1), Pricing::seniorDiscount(255, 2, 2));
        // More eligible persons than diners is capped
        $this->assertSame(Pricing::seniorDiscount(300, 3, 3), Pricing::seniorDiscount(300, 5, 3));
    }

    public function test_total_never_goes_below_zero(): void
    {
        $this->assertSame(487.14, Pricing::total(510, 72.86, 50));
        $this->assertSame(0.0, Pricing::total(10, 20));
    }

    public function test_vat_breakdown_without_discount_excludes_the_delivery_fee(): void
    {
        $this->assertSame(['vatable' => 365.18, 'vat' => 43.82, 'exempt' => 0.0], Pricing::vatBreakdown(409, 459, 50));
    }

    public function test_vat_breakdown_with_discount_makes_the_share_vat_exempt(): void
    {
        // ₱510, 1 of 2 diners: regular half ₱255 is VATable, the senior's half is exempt
        $this->assertSame(['vatable' => 227.68, 'vat' => 27.32, 'exempt' => 227.68], Pricing::vatBreakdown(510, 437.14, 0, 1, 2, 72.86));
    }

    public function test_quick_cash_amounts(): void
    {
        $this->assertSame([437.14, 450.0, 500.0, 1000], Pricing::quickCash(437.14));
        $this->assertSame([500.0, 1000], Pricing::quickCash(500));
    }
}
