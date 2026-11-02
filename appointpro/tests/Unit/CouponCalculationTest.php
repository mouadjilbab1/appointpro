<?php
declare(strict_types=1);

namespace AppointPro\Tests\Unit;

use AppointPro\Coupons\CouponService;
use PHPUnit\Framework\TestCase;

class CouponCalculationTest extends TestCase {
    public function testPercentageDiscountCalculation(): void {
        global $wpdb;
        $couponTable = $wpdb->prefix . 'appointpro_coupons';
        $wpdb->tables[$couponTable] = [
            [
                'id' => 1,
                'code' => 'SAVE20',
                'discount_type' => 'percentage',
                'discount_value' => 20.00,
                'min_order_amount' => 50.00,
                'usage_limit_total' => 100,
                'used_count' => 5,
                'expires_at' => '2029-12-31 23:59:59',
                'status' => 'active',
            ]
        ];

        $couponService = new CouponService();
        $result = $couponService->validateAndCalculateDiscount('SAVE20', 100.00, 1);

        $this->assertTrue($result['valid']);
        $this->assertEquals(20.00, $result['discount']);
    }

    public function testMinimumOrderAmountEnforcement(): void {
        global $wpdb;
        $couponTable = $wpdb->prefix . 'appointpro_coupons';
        $wpdb->tables[$couponTable] = [
            [
                'id' => 2,
                'code' => 'MIN100',
                'discount_type' => 'fixed',
                'discount_value' => 25.00,
                'min_order_amount' => 100.00,
                'usage_limit_total' => null,
                'used_count' => 0,
                'expires_at' => null,
                'status' => 'active',
            ]
        ];

        $couponService = new CouponService();
        $result = $couponService->validateAndCalculateDiscount('MIN100', 60.00, 1);

        $this->assertFalse($result['valid']);
        $this->assertEquals(0.00, $result['discount']);
    }
}
