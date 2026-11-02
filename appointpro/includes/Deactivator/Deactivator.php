<?php
declare(strict_types=1);

namespace AppointPro\Deactivator;

/**
 * Handles plugin deactivation routines.
 * Preserves all customer data and settings.
 */
class Deactivator {
    /**
     * Run deactivation cleanup.
     *
     * @return void
     */
    public static function deactivate(): void {
        wp_clear_scheduled_hook('appointpro_process_notification_queue');
        wp_clear_scheduled_hook('appointpro_cleanup_expired_locks');
        wp_clear_scheduled_hook('appointpro_process_waiting_list');
        wp_clear_scheduled_hook('appointpro_google_calendar_sync');
    }
}
