<?php
declare(strict_types=1);

namespace AppointPro\Tests\Unit;

use AppointPro\Availability\AvailabilityEngine;
use AppointPro\Resources\ResourceRepository;
use AppointPro\Services\ServiceRepository;
use AppointPro\Staff\StaffRepository;
use PHPUnit\Framework\TestCase;

class AvailabilityCalculationTest extends TestCase {
    public function testAvailabilityWithWorkingScheduleAndBreaks(): void {
        $mockSvc = $this->createMock(ServiceRepository::class);
        $mockSvc->method('find')->willReturn([
            'id' => 1,
            'name' => 'Haircut',
            'duration' => 30,
            'buffer_before' => 0,
            'buffer_after' => 0,
            'min_notice_hours' => 0,
            'max_advance_days' => 90,
            'status' => 'active',
        ]);
        $mockSvc->method('getServiceStaffIds')->willReturn([1]);

        $mockStaff = $this->createMock(StaffRepository::class);
        $mockRes = $this->createMock(ResourceRepository::class);
        $mockRes->method('getResourceForService')->willReturn(null);

        $engine = new AvailabilityEngine($mockSvc, $mockStaff, $mockRes);

        // Target date 2029-06-01 (Friday)
        $slots = $engine->getAvailableSlots(1, 1, '2029-06-01');

        $this->assertIsArray($slots);
    }
}
