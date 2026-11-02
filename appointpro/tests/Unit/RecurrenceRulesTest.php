<?php
declare(strict_types=1);

namespace AppointPro\Tests\Unit;

use AppointPro\Recurring\RecurringEngine;
use PHPUnit\Framework\TestCase;

class RecurrenceRulesTest extends TestCase {
    public function testWeeklyRecurrenceGeneration(): void {
        $engine = new RecurringEngine();
        $initial = '2026-11-02 09:00:00';
        $duration = 60; // 1 hour
        $occurrences = 4;
        $interval = 1; // every 1 week

        $dates = $engine->generateOccurrenceDates($initial, $duration, 'weekly', $interval, $occurrences);

        $this->assertCount(4, $dates);
        $this->assertEquals('2026-11-02 09:00:00', $dates[0]['start']);
        $this->assertEquals('2026-11-02 10:00:00', $dates[0]['end']);

        $this->assertEquals('2026-11-09 09:00:00', $dates[1]['start']);
        $this->assertEquals('2026-11-16 09:00:00', $dates[2]['start']);
        $this->assertEquals('2026-11-23 09:00:00', $dates[3]['start']);
    }

    public function testDailyRecurrenceGeneration(): void {
        $engine = new RecurringEngine();
        $initial = '2026-11-01 14:00:00';
        $dates = $engine->generateOccurrenceDates($initial, 30, 'daily', 2, 3); // every 2 days

        $this->assertCount(3, $dates);
        $this->assertEquals('2026-11-01 14:00:00', $dates[0]['start']);
        $this->assertEquals('2026-11-03 14:00:00', $dates[1]['start']);
        $this->assertEquals('2026-11-05 14:00:00', $dates[2]['start']);
    }
}
