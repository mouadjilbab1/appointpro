<?php
declare(strict_types=1);

namespace AppointPro\Tests\Integration;

use AppointPro\Booking\BookingEngine;
use AppointPro\Customers\CustomerRepository;
use AppointPro\Resources\ResourceRepository;
use AppointPro\Services\ServiceRepository;
use AppointPro\Staff\StaffRepository;
use PHPUnit\Framework\TestCase;

class AppointmentLifecycleTest extends TestCase {
    public function testAppointmentCreationAndTransitions(): void {
        $mockSvc = $this->createMock(ServiceRepository::class);
        $mockSvc->method('find')->willReturn([
            'id' => 10,
            'name' => 'Consultation',
            'duration' => 45,
            'price' => 120.00,
            'status' => 'active',
            'requires_approval' => 0,
        ]);

        $mockStaff = $this->createMock(StaffRepository::class);
        $mockCust = $this->createMock(CustomerRepository::class);
        $mockCust->method('findOrCreate')->willReturn(101);

        $mockRes = $this->createMock(ResourceRepository::class);
        $mockRes->method('getResourceForService')->willReturn(null);

        $engine = new BookingEngine($mockSvc, $mockStaff, $mockCust, $mockRes);

        $payload = [
            'service_id' => 10,
            'staff_id' => 5,
            'start_time' => '2026-11-20 10:00:00',
            'end_time' => '2026-11-20 10:45:00',
            'customer' => [
                'first_name' => 'Alice',
                'last_name' => 'Smith',
                'email' => 'alice@example.com',
                'phone' => '+15551234567',
            ],
            'customer_notes' => 'First consultation',
        ];

        $result = $engine->createAppointment($payload);

        $this->assertTrue($result['success']);
        $this->assertNotEmpty($result['booking_reference']);
        $this->assertEquals('confirmed', $result['status']);
        $this->assertEquals(120.00, $result['final_price']);

        // Test Cancellation
        $cancelled = $engine->cancel($result['appointment_id'], 'Client requested cancel');
        $this->assertTrue($cancelled);
    }
}
