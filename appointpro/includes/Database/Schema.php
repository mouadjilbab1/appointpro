<?php
declare(strict_types=1);

namespace AppointPro\Database;

/**
 * AppointPro Database Schema Definitions.
 */
class Schema {
    /**
     * Get SQL definitions for all plugin tables.
     *
     * @param string $prefix
     * @param string $collate
     * @return array<string, string>
     */
    public static function getTableDefinitions(string $prefix, string $collate): array {
        return [
            // 1. Categories
            "{$prefix}appointpro_categories" => "CREATE TABLE `{$prefix}appointpro_categories` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(191) NOT NULL,
                `description` TEXT NULL,
                `color` VARCHAR(32) DEFAULT '#4f46e5',
                `position` INT DEFAULT 0,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`)
            ) {$collate};",

            // 2. Services
            "{$prefix}appointpro_services" => "CREATE TABLE `{$prefix}appointpro_services` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `category_id` BIGINT UNSIGNED NULL,
                `name` VARCHAR(191) NOT NULL,
                `description` LONGTEXT NULL,
                `duration` INT UNSIGNED NOT NULL DEFAULT 30,
                `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `deposit_type` ENUM('none','fixed','percentage') DEFAULT 'none',
                `deposit_amount` DECIMAL(10,2) DEFAULT 0.00,
                `buffer_before` INT UNSIGNED DEFAULT 0,
                `buffer_after` INT UNSIGNED DEFAULT 0,
                `min_notice_hours` INT UNSIGNED DEFAULT 2,
                `max_advance_days` INT UNSIGNED DEFAULT 90,
                `capacity_min` INT UNSIGNED DEFAULT 1,
                `capacity_max` INT UNSIGNED DEFAULT 1,
                `color` VARCHAR(32) DEFAULT '#3b82f6',
                `image_url` VARCHAR(500) NULL,
                `requires_approval` TINYINT(1) DEFAULT 0,
                `allow_cancellation` TINYINT(1) DEFAULT 1,
                `cancellation_deadline_hours` INT UNSIGNED DEFAULT 24,
                `allow_rescheduling` TINYINT(1) DEFAULT 1,
                `is_recurring_enabled` TINYINT(1) DEFAULT 0,
                `status` ENUM('active','hidden','archived') DEFAULT 'active',
                `position` INT DEFAULT 0,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `category_id` (`category_id`),
                KEY `status` (`status`)
            ) {$collate};",

            // 3. Staff
            "{$prefix}appointpro_staff" => "CREATE TABLE `{$prefix}appointpro_staff` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `wp_user_id` BIGINT UNSIGNED NULL,
                `full_name` VARCHAR(191) NOT NULL,
                `email` VARCHAR(191) NOT NULL,
                `phone` VARCHAR(50) NULL,
                `avatar_url` VARCHAR(500) NULL,
                `bio` TEXT NULL,
                `auto_assign` TINYINT(1) DEFAULT 1,
                `status` ENUM('active','inactive') DEFAULT 'active',
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `wp_user_id` (`wp_user_id`),
                KEY `email` (`email`),
                KEY `status` (`status`)
            ) {$collate};",

            // 4. Staff Services
            "{$prefix}appointpro_staff_services" => "CREATE TABLE `{$prefix}appointpro_staff_services` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `staff_id` BIGINT UNSIGNED NOT NULL,
                `service_id` BIGINT UNSIGNED NOT NULL,
                `custom_price` DECIMAL(10,2) NULL,
                `custom_duration` INT UNSIGNED NULL,
                `capacity` INT UNSIGNED NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `staff_service` (`staff_id`, `service_id`),
                KEY `service_id` (`service_id`)
            ) {$collate};",

            // 5. Locations
            "{$prefix}appointpro_locations" => "CREATE TABLE `{$prefix}appointpro_locations` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(191) NOT NULL,
                `address` TEXT NULL,
                `phone` VARCHAR(50) NULL,
                `email` VARCHAR(191) NULL,
                `timezone` VARCHAR(100) DEFAULT 'UTC',
                `status` ENUM('active','inactive') DEFAULT 'active',
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `status` (`status`)
            ) {$collate};",

            // 6. Resources
            "{$prefix}appointpro_resources" => "CREATE TABLE `{$prefix}appointpro_resources` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(191) NOT NULL,
                `type` VARCHAR(50) DEFAULT 'room',
                `capacity` INT UNSIGNED DEFAULT 1,
                `status` ENUM('active','maintenance','inactive') DEFAULT 'active',
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `status` (`status`)
            ) {$collate};",

            // 7. Resource Services
            "{$prefix}appointpro_resource_services" => "CREATE TABLE `{$prefix}appointpro_resource_services` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `resource_id` BIGINT UNSIGNED NOT NULL,
                `service_id` BIGINT UNSIGNED NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `resource_service` (`resource_id`, `service_id`),
                KEY `service_id` (`service_id`)
            ) {$collate};",

            // 8. Schedules
            "{$prefix}appointpro_schedules" => "CREATE TABLE `{$prefix}appointpro_schedules` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `staff_id` BIGINT UNSIGNED NOT NULL,
                `location_id` BIGINT UNSIGNED NULL,
                `day_of_week` TINYINT UNSIGNED NOT NULL,
                `start_time` TIME NOT NULL,
                `end_time` TIME NOT NULL,
                `is_working` TINYINT(1) DEFAULT 1,
                PRIMARY KEY (`id`),
                KEY `staff_schedule_idx` (`staff_id`, `day_of_week`),
                KEY `location_id` (`location_id`)
            ) {$collate};",

            // 9. Schedule Breaks
            "{$prefix}appointpro_schedule_breaks" => "CREATE TABLE `{$prefix}appointpro_schedule_breaks` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `schedule_id` BIGINT UNSIGNED NOT NULL,
                `start_time` TIME NOT NULL,
                `end_time` TIME NOT NULL,
                `label` VARCHAR(100) DEFAULT 'Lunch',
                PRIMARY KEY (`id`),
                KEY `schedule_id` (`schedule_id`)
            ) {$collate};",

            // 10. Special Days (Holidays & Custom Days)
            "{$prefix}appointpro_special_days" => "CREATE TABLE `{$prefix}appointpro_special_days` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `staff_id` BIGINT UNSIGNED NULL,
                `start_date` DATE NOT NULL,
                `end_date` DATE NOT NULL,
                `start_time` TIME NULL,
                `end_time` TIME NULL,
                `is_day_off` TINYINT(1) DEFAULT 1,
                `reason` VARCHAR(191) NULL,
                PRIMARY KEY (`id`),
                KEY `staff_date_idx` (`staff_id`, `start_date`, `end_date`)
            ) {$collate};",

            // 11. Customers
            "{$prefix}appointpro_customers" => "CREATE TABLE `{$prefix}appointpro_customers` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `wp_user_id` BIGINT UNSIGNED NULL,
                `first_name` VARCHAR(100) NOT NULL,
                `last_name` VARCHAR(100) NOT NULL,
                `email` VARCHAR(191) NOT NULL,
                `phone` VARCHAR(50) NULL,
                `notes` TEXT NULL,
                `locale` VARCHAR(10) DEFAULT 'en_US',
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `wp_user_id` (`wp_user_id`),
                KEY `email` (`email`),
                KEY `phone` (`phone`)
            ) {$collate};",

            // 12. Appointments
            "{$prefix}appointpro_appointments" => "CREATE TABLE `{$prefix}appointpro_appointments` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `booking_reference` VARCHAR(32) NOT NULL,
                `service_id` BIGINT UNSIGNED NOT NULL,
                `staff_id` BIGINT UNSIGNED NOT NULL,
                `location_id` BIGINT UNSIGNED NULL,
                `start_time` DATETIME NOT NULL,
                `end_time` DATETIME NOT NULL,
                `status` ENUM('pending','confirmed','cancelled','rejected','completed','no-show') DEFAULT 'confirmed',
                `total_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `final_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `paid_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `payment_status` ENUM('unpaid','partial','paid','refunded') DEFAULT 'unpaid',
                `customer_notes` TEXT NULL,
                `internal_notes` TEXT NULL,
                `series_id` BIGINT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL,
                `updated_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `booking_reference` (`booking_reference`),
                KEY `service_id` (`service_id`),
                KEY `staff_id` (`staff_id`),
                KEY `location_id` (`location_id`),
                KEY `start_time` (`start_time`),
                KEY `end_time` (`end_time`),
                KEY `status` (`status`),
                KEY `payment_status` (`payment_status`),
                KEY `series_id` (`series_id`)
            ) {$collate};",

            // 13. Appointment Customers (Participants)
            "{$prefix}appointpro_appointment_customers" => "CREATE TABLE `{$prefix}appointpro_appointment_customers` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `appointment_id` BIGINT UNSIGNED NOT NULL,
                `customer_id` BIGINT UNSIGNED NOT NULL,
                `number_of_persons` INT UNSIGNED DEFAULT 1,
                `status` ENUM('registered','cancelled') DEFAULT 'registered',
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `appointment_id` (`appointment_id`),
                KEY `customer_id` (`customer_id`)
            ) {$collate};",

            // 14. Extras
            "{$prefix}appointpro_extras" => "CREATE TABLE `{$prefix}appointpro_extras` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `service_id` BIGINT UNSIGNED NOT NULL,
                `name` VARCHAR(191) NOT NULL,
                `description` TEXT NULL,
                `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `duration` INT UNSIGNED DEFAULT 0,
                `max_quantity` INT UNSIGNED DEFAULT 1,
                `status` ENUM('active','inactive') DEFAULT 'active',
                PRIMARY KEY (`id`),
                KEY `service_id` (`service_id`)
            ) {$collate};",

            // 15. Appointment Extras
            "{$prefix}appointpro_appointment_extras" => "CREATE TABLE `{$prefix}appointpro_appointment_extras` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `appointment_id` BIGINT UNSIGNED NOT NULL,
                `extra_id` BIGINT UNSIGNED NOT NULL,
                `quantity` INT UNSIGNED DEFAULT 1,
                `price` DECIMAL(10,2) NOT NULL,
                PRIMARY KEY (`id`),
                KEY `appointment_id` (`appointment_id`),
                KEY `extra_id` (`extra_id`)
            ) {$collate};",

            // 16. Resource Allocations
            "{$prefix}appointpro_resource_allocations" => "CREATE TABLE `{$prefix}appointpro_resource_allocations` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `appointment_id` BIGINT UNSIGNED NOT NULL,
                `resource_id` BIGINT UNSIGNED NOT NULL,
                `start_time` DATETIME NOT NULL,
                `end_time` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `appointment_id` (`appointment_id`),
                KEY `resource_time_idx` (`resource_id`, `start_time`, `end_time`)
            ) {$collate};",

            // 17. Payments
            "{$prefix}appointpro_payments" => "CREATE TABLE `{$prefix}appointpro_payments` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `appointment_id` BIGINT UNSIGNED NOT NULL,
                `gateway` VARCHAR(50) NOT NULL,
                `transaction_id` VARCHAR(191) NULL,
                `amount` DECIMAL(10,2) NOT NULL,
                `currency` VARCHAR(10) NOT NULL DEFAULT 'USD',
                `type` ENUM('full','deposit','remaining') DEFAULT 'full',
                `status` ENUM('pending','completed','failed','refunded') DEFAULT 'pending',
                `gateway_response` LONGTEXT NULL,
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `appointment_id` (`appointment_id`),
                KEY `transaction_id` (`transaction_id`),
                KEY `status` (`status`)
            ) {$collate};",

            // 18. Coupons
            "{$prefix}appointpro_coupons" => "CREATE TABLE `{$prefix}appointpro_coupons` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `code` VARCHAR(50) NOT NULL,
                `discount_type` ENUM('fixed','percentage') NOT NULL,
                `discount_value` DECIMAL(10,2) NOT NULL,
                `min_order_amount` DECIMAL(10,2) DEFAULT 0.00,
                `usage_limit_total` INT UNSIGNED NULL,
                `usage_limit_per_customer` INT UNSIGNED DEFAULT 1,
                `used_count` INT UNSIGNED DEFAULT 0,
                `expires_at` DATETIME NULL,
                `status` ENUM('active','inactive') DEFAULT 'active',
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `code` (`code`),
                KEY `status` (`status`)
            ) {$collate};",

            // 19. Coupon Usage
            "{$prefix}appointpro_coupon_usage" => "CREATE TABLE `{$prefix}appointpro_coupon_usage` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `coupon_id` BIGINT UNSIGNED NOT NULL,
                `customer_id` BIGINT UNSIGNED NOT NULL,
                `appointment_id` BIGINT UNSIGNED NOT NULL,
                `discount_amount` DECIMAL(10,2) NOT NULL,
                `used_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `coupon_id` (`coupon_id`),
                KEY `customer_id` (`customer_id`),
                KEY `appointment_id` (`appointment_id`)
            ) {$collate};",

            // 20. Custom Fields
            "{$prefix}appointpro_custom_fields" => "CREATE TABLE `{$prefix}appointpro_custom_fields` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `label` VARCHAR(191) NOT NULL,
                `field_type` ENUM('text','textarea','number','select','checkbox','radio','date') NOT NULL,
                `options` TEXT NULL,
                `is_required` TINYINT(1) DEFAULT 0,
                `services` TEXT NULL,
                `position` INT DEFAULT 0,
                PRIMARY KEY (`id`)
            ) {$collate};",

            // 21. Custom Field Values
            "{$prefix}appointpro_custom_field_values" => "CREATE TABLE `{$prefix}appointpro_custom_field_values` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `appointment_id` BIGINT UNSIGNED NOT NULL,
                `field_id` BIGINT UNSIGNED NOT NULL,
                `value` LONGTEXT NULL,
                PRIMARY KEY (`id`),
                KEY `appointment_field_idx` (`appointment_id`, `field_id`)
            ) {$collate};",

            // 22. Notifications
            "{$prefix}appointpro_notifications" => "CREATE TABLE `{$prefix}appointpro_notifications` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `appointment_id` BIGINT UNSIGNED NULL,
                `recipient` VARCHAR(191) NOT NULL,
                `channel` ENUM('email','sms','whatsapp') NOT NULL,
                `event` VARCHAR(100) NOT NULL,
                `subject` VARCHAR(255) NULL,
                `message` LONGTEXT NOT NULL,
                `status` ENUM('queued','sent','failed') DEFAULT 'queued',
                `attempts` TINYINT UNSIGNED DEFAULT 0,
                `error_message` TEXT NULL,
                `scheduled_for` DATETIME NOT NULL,
                `sent_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                KEY `status_scheduled_idx` (`status`, `scheduled_for`),
                KEY `appointment_id` (`appointment_id`)
            ) {$collate};",

            // 23. Waiting List
            "{$prefix}appointpro_waiting_list" => "CREATE TABLE `{$prefix}appointpro_waiting_list` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `service_id` BIGINT UNSIGNED NOT NULL,
                `staff_id` BIGINT UNSIGNED NULL,
                `customer_id` BIGINT UNSIGNED NOT NULL,
                `desired_date` DATE NOT NULL,
                `status` ENUM('waiting','notified','booked','expired','cancelled') DEFAULT 'waiting',
                `offer_expires_at` DATETIME NULL,
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `waiting_list_lookup` (`service_id`, `desired_date`, `status`),
                KEY `customer_id` (`customer_id`)
            ) {$collate};",

            // 24. Recurring Series
            "{$prefix}appointpro_recurring_series" => "CREATE TABLE `{$prefix}appointpro_recurring_series` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `customer_id` BIGINT UNSIGNED NOT NULL,
                `frequency` ENUM('daily','weekly','monthly','custom') NOT NULL,
                `repeat_interval` INT UNSIGNED DEFAULT 1,
                `total_occurrences` INT UNSIGNED NOT NULL,
                `completed_occurrences` INT UNSIGNED DEFAULT 0,
                `status` ENUM('active','completed','cancelled') DEFAULT 'active',
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `customer_id` (`customer_id`),
                KEY `status` (`status`)
            ) {$collate};",

            // 25. Audit Logs
            "{$prefix}appointpro_audit_logs" => "CREATE TABLE `{$prefix}appointpro_audit_logs` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `user_id` BIGINT UNSIGNED NULL,
                `action` VARCHAR(100) NOT NULL,
                `entity_type` VARCHAR(50) NOT NULL,
                `entity_id` BIGINT UNSIGNED NOT NULL,
                `details` LONGTEXT NULL,
                `ip_address` VARCHAR(45) NULL,
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `entity_lookup` (`entity_type`, `entity_id`),
                KEY `action` (`action`),
                KEY `created_at` (`created_at`)
            ) {$collate};",

            // 26. Webhooks
            "{$prefix}appointpro_webhooks" => "CREATE TABLE `{$prefix}appointpro_webhooks` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(191) NOT NULL,
                `target_url` VARCHAR(500) NOT NULL,
                `secret_key` VARCHAR(100) NOT NULL,
                `events` TEXT NOT NULL,
                `status` ENUM('active','inactive') DEFAULT 'active',
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `status` (`status`)
            ) {$collate};",

            // 27. Webhook Logs
            "{$prefix}appointpro_webhook_logs" => "CREATE TABLE `{$prefix}appointpro_webhook_logs` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `webhook_id` BIGINT UNSIGNED NOT NULL,
                `event` VARCHAR(100) NOT NULL,
                `payload` LONGTEXT NOT NULL,
                `response_code` INT NULL,
                `response_body` TEXT NULL,
                `attempts` TINYINT UNSIGNED DEFAULT 1,
                `status` ENUM('success','failed') NOT NULL,
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `webhook_id` (`webhook_id`),
                KEY `created_at` (`created_at`)
            ) {$collate};",

            // 28. Integrations
            "{$prefix}appointpro_integrations" => "CREATE TABLE `{$prefix}appointpro_integrations` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `provider` VARCHAR(50) NOT NULL,
                `staff_id` BIGINT UNSIGNED NULL,
                `access_token` TEXT NULL,
                `refresh_token` TEXT NULL,
                `token_expires_at` DATETIME NULL,
                `settings` LONGTEXT NULL,
                `last_synced_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `provider_staff` (`provider`, `staff_id`),
                KEY `provider` (`provider`)
            ) {$collate};",

            // 29. Concurrency Lock Table
            "{$prefix}appointpro_slot_locks" => "CREATE TABLE `{$prefix}appointpro_slot_locks` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `staff_id` BIGINT UNSIGNED NOT NULL,
                `start_time` DATETIME NOT NULL,
                `end_time` DATETIME NOT NULL,
                `session_token` VARCHAR(64) NOT NULL,
                `expires_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `staff_slot_lock` (`staff_id`, `start_time`, `end_time`),
                KEY `session_token` (`session_token`),
                KEY `expires_at` (`expires_at`)
            ) {$collate};"
        ];
    }
}
