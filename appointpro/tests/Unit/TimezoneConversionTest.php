<?php
declare(strict_types=1);

namespace AppointPro\Tests\Unit;

use AppointPro\Helpers\DateTimeHelper;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

class TimezoneConversionTest extends TestCase {
    public function testToUtcConversion(): void {
        $nyTimezone = new DateTimeZone('America/New_York');
        // New York is UTC-5 (or UTC-4 during daylight saving)
        $localTime = '2026-11-15 10:00:00';
        $utcTime = DateTimeHelper::toUtc($localTime, $nyTimezone);

        $this->assertEquals('2026-11-15 15:00:00', $utcTime);
    }

    public function testToLocalConversion(): void {
        $dubaiTimezone = new DateTimeZone('Asia/Dubai'); // UTC+4 year-round
        $utcTime = '2026-11-15 12:00:00';
        $localTime = DateTimeHelper::toLocal($utcTime, $dubaiTimezone);

        $this->assertEquals('2026-11-15 16:00:00', $localTime);
    }

    public function testIntervalsOverlapDetection(): void {
        // Overlapping intervals: 10:00-11:00 and 10:30-11:30
        $overlap = DateTimeHelper::intervalsOverlap(
            '2026-11-15 10:00:00',
            '2026-11-15 11:00:00',
            '2026-11-15 10:30:00',
            '2026-11-15 11:30:00'
        );
        $this->assertTrue($overlap);

        // Non-overlapping adjacent intervals: 10:00-11:00 and 11:00-12:00
        $noOverlap = DateTimeHelper::intervalsOverlap(
            '2026-11-15 10:00:00',
            '2026-11-15 11:00:00',
            '2026-11-15 11:00:00',
            '2026-11-15 12:00:00'
        );
        $this->assertFalse($noOverlap);
    }
}
