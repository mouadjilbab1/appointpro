<?php
declare(strict_types=1);

namespace AppointPro\Tests\Unit;

use AppointPro\Security\Security;
use PHPUnit\Framework\TestCase;

class SecurityTest extends TestCase {
    public function testNonceValidation(): void {
        $action = 'test_appointpro_action';
        $nonce = Security::createNonce($action);

        $this->assertNotEmpty($nonce);
        $this->assertTrue(Security::verifyNonce($nonce, $action));
        $this->assertFalse(Security::verifyNonce('invalid_nonce', $action));
        $this->assertFalse(Security::verifyNonce(null, $action));
    }

    public function testInputSanitization(): void {
        $dirty = [
            'name' => '<script>alert("xss")</script>John Doe',
            'email' => '  john.doe@example.com ',
            'nested' => [
                'field' => '<b>bold</b> text',
            ]
        ];

        $clean = Security::sanitizeRecursive($dirty);

        $this->assertEquals('John Doe', $clean['name']);
        $this->assertEquals('john.doe@example.com', Security::sanitizeEmail($dirty['email']));
        $this->assertEquals('bold text', $clean['nested']['field']);
    }

    public function testSecureTokenGeneration(): void {
        $token1 = Security::generateSecureToken(16);
        $token2 = Security::generateSecureToken(16);

        $this->assertEquals(32, strlen($token1));
        $this->assertEquals(32, strlen($token2));
        $this->assertNotEquals($token1, $token2);
    }

    public function testRateLimiting(): void {
        $actionKey = 'test_booking_rate_' . uniqid();
        $allowed = Security::checkRateLimit($actionKey, 2, 10);
        $this->assertTrue($allowed);

        $allowed2 = Security::checkRateLimit($actionKey, 2, 10);
        $this->assertTrue($allowed2);

        $blocked = Security::checkRateLimit($actionKey, 2, 10);
        $this->assertFalse($blocked);
    }
}
