<?php
declare(strict_types=1);

namespace AppointPro\Tests\Unit;

use AppointPro\Booking\BookingEngine;
use AppointPro\Services\ServiceRepository;
use PHPUnit\Framework\TestCase;

class PricingCalculationTest extends TestCase {
    public function testBasePriceAndDepositCalculation(): void {
        $mockServiceRepo = $this->createMock(ServiceRepository::class);
        $mockServiceRepo->method('find')->willReturn([
            'id' => 1,
            'name' => 'Haircut',
            'price' => 50.00,
            'deposit_type' => 'percentage',
            'deposit_amount' => 20.00, // 20%
        ]);

        $bookingEngine = new BookingEngine($mockServiceRepo);
        $pricing = $bookingEngine->calculatePricing(1, 1, [], 1, null);

        $this->assertEquals(50.00, $pricing['base_price']);
        $this->assertEquals(0.00, $pricing['extras_price']);
        $this->assertEquals(50.00, $pricing['subtotal']);
        $this->assertEquals(0.00, $pricing['discount']);
        $this->assertEquals(50.00, $pricing['final_price']);
        $this->assertEquals(10.00, $pricing['deposit_amount']); // 20% of 50
        $this->assertEquals(40.00, $pricing['balance_due']);
    }

    public function testGroupBookingCapacityMultiplier(): void {
        $mockServiceRepo = $this->createMock(ServiceRepository::class);
        $mockServiceRepo->method('find')->willReturn([
            'id' => 2,
            'name' => 'Yoga Class',
            'price' => 25.00,
            'deposit_type' => 'fixed',
            'deposit_amount' => 15.00,
        ]);

        $bookingEngine = new BookingEngine($mockServiceRepo);
        $pricing = $bookingEngine->calculatePricing(2, 1, [], 4, null); // 4 participants

        $this->assertEquals(100.00, $pricing['base_price']);
        $this->assertEquals(100.00, $pricing['final_price']);
        $this->assertEquals(15.00, $pricing['deposit_amount']);
        $this->assertEquals(85.00, $pricing['balance_due']);
    }
}
