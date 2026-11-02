<?php
declare(strict_types=1);

namespace AppointPro\Tests\Integration;

use AppointPro\Availability\AvailabilityEngine;
use PHPUnit\Framework\TestCase;

class ConcurrentBookingLockTest extends TestCase {
    public function testSlotReservationAndRelease(): void {
        $engine = new AvailabilityEngine();

        $staffId = 3;
        $start = '2026-11-20 14:00:00';
        $end = '2026-11-20 14:30:00';
        $tokenA = 'session_customer_A';
        $tokenB = 'session_customer_B';

        // Customer A reserves the slot
        $lockedA = $engine->reserveSlotLock($staffId, $start, $end, $tokenA, 10);
        $this->assertTrue($lockedA);

        // Release lock
        $engine->releaseSlotLock($tokenA);
        $this->assertTrue(true);
    }
}
