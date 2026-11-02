<?php
declare(strict_types=1);

namespace AppointPro\REST;

use AppointPro\Permissions\PermissionsManager;
use AppointPro\Security\Security;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST Controller for Outgoing Webhook Event Subscriptions and Delivery Logs.
 */
class WebhooksController {
    public function registerRoutes(): void {
        register_rest_route('appointpro/v1', '/webhooks', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getWebhooks'],
                'permission_callback' => [$this, 'checkAdminPermission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'saveWebhook'],
                'permission_callback' => [$this, 'checkAdminPermission'],
            ]
        ]);

        register_rest_route('appointpro/v1', '/webhooks/(?P<id>\d+)', [
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => [$this, 'deleteWebhook'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);

        register_rest_route('appointpro/v1', '/webhooks/logs', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'getLogs'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);
    }

    public function checkAdminPermission(): bool {
        return current_user_can(PermissionsManager::CAP_MANAGE_SETTINGS) || current_user_can('manage_options');
    }

    public function getWebhooks(): WP_REST_Response {
        global $wpdb;
        $table = $wpdb->prefix . 'appointpro_webhooks';
        $hooks = $wpdb->get_results("SELECT * FROM `{$table}` ORDER BY created_at DESC", ARRAY_A) ?: [];
        return new WP_REST_Response(['success' => true, 'data' => $hooks], 200);
    }

    public function saveWebhook(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $table = $wpdb->prefix . 'appointpro_webhooks';
        $params = $request->get_json_params() ?: $request->get_params();
        $id = !empty($params['id']) ? (int) $params['id'] : null;

        $record = [
            'name' => sanitize_text_field($params['name'] ?? ''),
            'target_url' => esc_url_raw($params['target_url'] ?? ''),
            'secret_key' => !empty($params['secret_key']) ? sanitize_text_field($params['secret_key']) : Security::generateSecureToken(24),
            'events' => json_encode($params['events'] ?? ['appointment.created']),
            'status' => in_array($params['status'] ?? '', ['active', 'inactive'], true) ? $params['status'] : 'active',
        ];

        if ($id && $id > 0) {
            $wpdb->update($table, $record, ['id' => $id]);
            $hookId = $id;
        } else {
            $record['created_at'] = gmdate('Y-m-d H:i:s');
            $wpdb->insert($table, $record);
            $hookId = (int) $wpdb->insert_id;
        }

        $saved = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$table}` WHERE id = %d", $hookId), ARRAY_A);
        return new WP_REST_Response(['success' => true, 'data' => $saved], 200);
    }

    public function deleteWebhook(WP_REST_Request $request): WP_REST_Response {
        $id = (int) $request->get_param('id');
        global $wpdb;
        $table = $wpdb->prefix . 'appointpro_webhooks';
        $success = (bool) $wpdb->delete($table, ['id' => $id]);
        return new WP_REST_Response(['success' => $success], 200);
    }

    public function getLogs(): WP_REST_Response {
        global $wpdb;
        $table = $wpdb->prefix . 'appointpro_webhook_logs';
        $logs = $wpdb->get_results("SELECT * FROM `{$table}` ORDER BY created_at DESC LIMIT 50", ARRAY_A) ?: [];
        return new WP_REST_Response(['success' => true, 'data' => $logs], 200);
    }
}
