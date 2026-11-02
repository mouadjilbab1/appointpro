<?php
declare(strict_types=1);

namespace AppointPro\Tests\Integration;

use AppointPro\Database\MigrationManager;
use AppointPro\Database\Schema;
use PHPUnit\Framework\TestCase;

class DatabaseMigrationsTest extends TestCase {
    public function testSchemaContainsAllRequiredTables(): void {
        $tables = Schema::getTableDefinitions('wp_', 'DEFAULT CHARSET=utf8mb4');

        $this->assertArrayHasKey('wp_appointpro_categories', $tables);
        $this->assertArrayHasKey('wp_appointpro_services', $tables);
        $this->assertArrayHasKey('wp_appointpro_staff', $tables);
        $this->assertArrayHasKey('wp_appointpro_staff_services', $tables);
        $this->assertArrayHasKey('wp_appointpro_locations', $tables);
        $this->assertArrayHasKey('wp_appointpro_resources', $tables);
        $this->assertArrayHasKey('wp_appointpro_resource_services', $tables);
        $this->assertArrayHasKey('wp_appointpro_schedules', $tables);
        $this->assertArrayHasKey('wp_appointpro_schedule_breaks', $tables);
        $this->assertArrayHasKey('wp_appointpro_special_days', $tables);
        $this->assertArrayHasKey('wp_appointpro_customers', $tables);
        $this->assertArrayHasKey('wp_appointpro_appointments', $tables);
        $this->assertArrayHasKey('wp_appointpro_appointment_customers', $tables);
        $this->assertArrayHasKey('wp_appointpro_extras', $tables);
        $this->assertArrayHasKey('wp_appointpro_appointment_extras', $tables);
        $this->assertArrayHasKey('wp_appointpro_payments', $tables);
        $this->assertArrayHasKey('wp_appointpro_coupons', $tables);
        $this->assertArrayHasKey('wp_appointpro_notifications', $tables);
        $this->assertArrayHasKey('wp_appointpro_waiting_list', $tables);
        $this->assertArrayHasKey('wp_appointpro_recurring_series', $tables);
        $this->assertArrayHasKey('wp_appointpro_audit_logs', $tables);
        $this->assertArrayHasKey('wp_appointpro_webhooks', $tables);
        $this->assertArrayHasKey('wp_appointpro_webhook_logs', $tables);
        $this->assertArrayHasKey('wp_appointpro_integrations', $tables);
        $this->assertArrayHasKey('wp_appointpro_slot_locks', $tables);

        $this->assertGreaterThanOrEqual(25, count($tables));
    }

    public function testMigrationRunsIdempotently(): void {
        $migrator = new MigrationManager();
        $res = $migrator->migrate();
        $this->assertTrue($res);
    }
}
