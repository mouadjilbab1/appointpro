<?php
/**
 * AppointPro Uninstall Handler
 *
 * Triggered when the plugin is deleted via the WordPress Admin.
 * Respects data preservation settings.
 */

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Check administrator preference for data deletion
$delete_data = get_option('appointpro_delete_data_on_uninstall', 0);

if ((int)$delete_data === 1) {
    global $wpdb;

    $tables = [
        $wpdb->prefix . 'appointpro_slot_locks',
        $wpdb->prefix . 'appointpro_integrations',
        $wpdb->prefix . 'appointpro_webhook_logs',
        $wpdb->prefix . 'appointpro_webhooks',
        $wpdb->prefix . 'appointpro_audit_logs',
        $wpdb->prefix . 'appointpro_recurring_series',
        $wpdb->prefix . 'appointpro_waiting_list',
        $wpdb->prefix . 'appointpro_notifications',
        $wpdb->prefix . 'appointpro_custom_field_values',
        $wpdb->prefix . 'appointpro_custom_fields',
        $wpdb->prefix . 'appointpro_coupon_usage',
        $wpdb->prefix . 'appointpro_coupons',
        $wpdb->prefix . 'appointpro_payments',
        $wpdb->prefix . 'appointpro_resource_allocations',
        $wpdb->prefix . 'appointpro_appointment_extras',
        $wpdb->prefix . 'appointpro_extras',
        $wpdb->prefix . 'appointpro_appointment_customers',
        $wpdb->prefix . 'appointpro_appointments',
        $wpdb->prefix . 'appointpro_customers',
        $wpdb->prefix . 'appointpro_special_days',
        $wpdb->prefix . 'appointpro_schedule_breaks',
        $wpdb->prefix . 'appointpro_schedules',
        $wpdb->prefix . 'appointpro_resource_services',
        $wpdb->prefix . 'appointpro_resources',
        $wpdb->prefix . 'appointpro_locations',
        $wpdb->prefix . 'appointpro_staff_services',
        $wpdb->prefix . 'appointpro_staff',
        $wpdb->prefix . 'appointpro_services',
        $wpdb->prefix . 'appointpro_categories',
    ];

    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS `{$table}`");
    }

    // Delete registered options
    $options = [
        'appointpro_version',
        'appointpro_db_version',
        'appointpro_settings',
        'appointpro_delete_data_on_uninstall',
        'appointpro_setup_completed',
    ];

    foreach ($options as $opt) {
        delete_option($opt);
    }

    // Clean up scheduled cron jobs
    wp_clear_scheduled_hook('appointpro_process_notification_queue');
    wp_clear_scheduled_hook('appointpro_cleanup_expired_locks');
    wp_clear_scheduled_hook('appointpro_process_waiting_list');
    wp_clear_scheduled_hook('appointpro_google_calendar_sync');
}
