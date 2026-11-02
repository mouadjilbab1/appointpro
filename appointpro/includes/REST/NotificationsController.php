<?php
declare(strict_types=1);

namespace AppointPro\REST;

use AppointPro\Notifications\NotificationDispatcher;
use AppointPro\Permissions\PermissionsManager;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST Controller for Notification Queue, Dispatch Logs, and Templates.
 */
class NotificationsController {
    public function registerRoutes(): void {
        register_rest_route('appointpro/v1', '/notifications', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'getNotificationLogs'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);

        register_rest_route('appointpro/v1', '/notifications/process', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'processQueue'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);
    }

    public function checkAdminPermission(): bool {
        return current_user_can(PermissionsManager::CAP_MANAGE_SETTINGS) || current_user_can('manage_options');
    }

    public function getNotificationLogs(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $table = $wpdb->prefix . 'appointpro_notifications';
        $logs = $wpdb->get_results("SELECT * FROM `{$table}` ORDER BY scheduled_for DESC LIMIT 50", ARRAY_A) ?: [];
        return new WP_REST_Response(['success' => true, 'data' => $logs], 200);
    }

    public function processQueue(): WP_REST_Response {
        $dispatcher = new NotificationDispatcher();
        $processed = $dispatcher->processQueue(50);
        return new WP_REST_Response(['success' => true, 'processed' => $processed], 200);
    }
}
