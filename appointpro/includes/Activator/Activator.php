<?php
declare(strict_types=1);

namespace AppointPro\Activator;

use AppointPro\Database\MigrationManager;
use AppointPro\Permissions\PermissionsManager;

/**
 * Handles plugin activation routines.
 */
class Activator {
    /**
     * Run activation procedures.
     *
     * @return void
     */
    public static function activate(): void {
        // Run database migrations
        $migrator = new MigrationManager();
        $migrator->migrate();

        // Register custom roles and capabilities
        PermissionsManager::registerRolesAndCaps();

        // Register WP-Cron scheduled hooks
        if (!wp_next_scheduled('appointpro_process_notification_queue')) {
            wp_schedule_event(time(), 'five_minutes', 'appointpro_process_notification_queue');
        }

        if (!wp_next_scheduled('appointpro_cleanup_expired_locks')) {
            wp_schedule_event(time(), 'hourly', 'appointpro_cleanup_expired_locks');
        }

        if (!wp_next_scheduled('appointpro_process_waiting_list')) {
            wp_schedule_event(time(), 'fifteen_minutes', 'appointpro_process_waiting_list');
        }

        if (!wp_next_scheduled('appointpro_google_calendar_sync')) {
            wp_schedule_event(time(), 'fifteen_minutes', 'appointpro_google_calendar_sync');
        }

        update_option('appointpro_version', APPOINTPRO_VERSION);
    }
}
