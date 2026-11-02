<?php
declare(strict_types=1);

namespace AppointPro\REST;

use AppointPro\Integrations\GoogleCalendarService;
use AppointPro\Permissions\PermissionsManager;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST Controller for Third-party Integrations (Google Calendar, Zoom, WooCommerce).
 */
class IntegrationsController {
    public function registerRoutes(): void {
        register_rest_route('appointpro/v1', '/integrations', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'getIntegrationsStatus'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);

        register_rest_route('appointpro/v1', '/integrations/google-calendar', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'saveGoogleCalendarConfig'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);
    }

    public function checkAdminPermission(): bool {
        return current_user_can(PermissionsManager::CAP_MANAGE_SETTINGS) || current_user_can('manage_options');
    }

    public function getIntegrationsStatus(): WP_REST_Response {
        $gcal = new GoogleCalendarService();
        return new WP_REST_Response([
            'success' => true,
            'data' => [
                'google_calendar' => [
                    'connected' => $gcal->isConnected(),
                    'title' => 'Google Calendar (2-Way Sync)',
                    'requires_credentials' => true,
                ],
                'woocommerce' => [
                    'active' => class_exists('WooCommerce'),
                    'title' => 'WooCommerce Checkout Bridge',
                ],
            ]
        ], 200);
    }

    public function saveGoogleCalendarConfig(WP_REST_Request $request): WP_REST_Response {
        $params = $request->get_json_params() ?: $request->get_params();
        $clientId = sanitize_text_field($params['client_id'] ?? '');
        $clientSecret = sanitize_text_field($params['client_secret'] ?? '');

        update_option('appointpro_google_oauth_settings', [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]);

        return new WP_REST_Response(['success' => true, 'message' => __('Google OAuth settings updated', 'appointpro')], 200);
    }
}
