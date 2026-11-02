<?php
declare(strict_types=1);

namespace AppointPro\Tests\Unit;

use AppointPro\Helpers\Formatting;
use PHPUnit\Framework\TestCase;

class DurationCalculationTest extends TestCase {
    public function testFormattingMinutesOnly(): void {
        $formatted = Formatting::formatDuration(45);
        $this->assertStringContainsString('45', $formatted);
    }

    public function testFormattingExactHours(): void {
        $formatted = Formatting::formatDuration(120);
        $this->assertStringContainsString('2', $formatted);
    }

    public function testFormattingHoursAndMinutes(): void {
        $formatted = Formatting::formatDuration(90);
        $this->assertStringContainsString('1', $formatted);
        $this->assertStringContainsString('30', $formatted);
    }
}
