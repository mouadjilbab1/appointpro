<?php
declare(strict_types=1);

namespace AppointPro\REST;

use AppointPro\AI\AIAssistant;
use AppointPro\Permissions\PermissionsManager;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST Controller for Optional AI Booking Assistant.
 */
class AIController {
    protected AIAssistant $assistant;

    public function __construct() {
        $this->assistant = new AIAssistant();
    }

    public function registerRoutes(): void {
        register_rest_route('appointpro/v1', '/ai/status', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'getStatus'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('appointpro/v1', '/ai/chat', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'chat'],
            'permission_callback' => '__return_true', // Public for customer guidance
        ]);

        register_rest_route('appointpro/v1', '/ai/settings', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'saveSettings'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);
    }

    public function checkAdminPermission(): bool {
        return current_user_can(PermissionsManager::CAP_MANAGE_SETTINGS) || current_user_can('manage_options');
    }

    public function getStatus(): WP_REST_Response {
        $settings = (array) get_option('appointpro_ai_settings', []);
        return new WP_REST_Response([
            'success' => true,
            'enabled' => $this->assistant->isEnabled(),
            'provider' => $settings['provider'] ?? 'openai',
            'has_key' => !empty($settings['api_key']),
        ], 200);
    }

    public function chat(WP_REST_Request $request): WP_REST_Response {
        $params = $request->get_json_params() ?: $request->get_params();
        $message = sanitize_text_field($params['message'] ?? '');

        if (empty($message)) {
            return new WP_REST_Response(['success' => false, 'message' => 'Empty message'], 400);
        }

        $result = $this->assistant->query($message);
        return new WP_REST_Response(['success' => true, 'data' => $result], 200);
    }

    public function saveSettings(WP_REST_Request $request): WP_REST_Response {
        $params = $request->get_json_params() ?: $request->get_params();

        $cleanSettings = [
            'enabled' => !empty($params['enabled']) ? 1 : 0,
            'provider' => sanitize_text_field($params['provider'] ?? 'openai'),
            'api_key' => sanitize_text_field($params['api_key'] ?? ''),
            'model' => sanitize_text_field($params['model'] ?? 'gpt-4o-mini'),
        ];

        update_option('appointpro_ai_settings', $cleanSettings);

        return new WP_REST_Response(['success' => true, 'message' => __('AI settings updated', 'appointpro')], 200);
    }
}
