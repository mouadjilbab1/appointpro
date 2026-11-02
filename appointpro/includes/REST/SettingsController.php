<?php
declare(strict_types=1);

namespace AppointPro\REST;

use AppointPro\Helpers\Currency;
use AppointPro\Permissions\PermissionsManager;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST Controller for Plugin Settings, Appearance, and Custom Form Fields.
 */
class SettingsController {
    public function registerRoutes(): void {
        register_rest_route('appointpro/v1', '/settings', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getSettings'],
                'permission_callback' => [$this, 'checkAdminPermission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'saveSettings'],
                'permission_callback' => [$this, 'checkAdminPermission'],
            ]
        ]);

        register_rest_route('appointpro/v1', '/settings/custom-fields', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getCustomFields'],
                'permission_callback' => '__return_true', // Public for checkout form
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'saveCustomFields'],
                'permission_callback' => [$this, 'checkAdminPermission'],
            ]
        ]);
    }

    public function checkAdminPermission(): bool {
        return current_user_can(PermissionsManager::CAP_MANAGE_SETTINGS) || current_user_can('manage_options');
    }

    public function getSettings(): WP_REST_Response {
        $general = (array) get_option('appointpro_settings', []);
        $payment = (array) get_option('appointpro_payment_settings', []);
        $ai = (array) get_option('appointpro_ai_settings', []);

        return new WP_REST_Response([
            'success' => true,
            'data' => [
                'general' => $general,
                'payment' => $payment,
                'ai' => $ai,
                'currencies' => Currency::getCurrencies(),
            ]
        ], 200);
    }

    public function saveSettings(WP_REST_Request $request): WP_REST_Response {
        $params = $request->get_json_params() ?: $request->get_params();

        if (isset($params['general'])) {
            $general = (array) $params['general'];
            $cleanGeneral = [
                'business_name' => sanitize_text_field($general['business_name'] ?? get_bloginfo('name')),
                'business_email' => sanitize_email($general['business_email'] ?? get_bloginfo('admin_email')),
                'business_phone' => sanitize_text_field($general['business_phone'] ?? ''),
                'timezone' => sanitize_text_field($general['timezone'] ?? wp_timezone_string()),
                'currency' => sanitize_text_field($general['currency'] ?? 'USD'),
                'currency_symbol' => sanitize_text_field($general['currency_symbol'] ?? '$'),
                'currency_position' => ($general['currency_position'] ?? 'before') === 'after' ? 'after' : 'before',
                'date_format' => sanitize_text_field($general['date_format'] ?? 'Y-m-d'),
                'time_format' => sanitize_text_field($general['time_format'] ?? 'H:i'),
                'default_slot_step' => max(5, (int) ($general['default_slot_step'] ?? 30)),
                'allow_guest_booking' => !empty($general['allow_guest_booking']) ? 1 : 0,
                'auto_confirm_appointments' => !empty($general['auto_confirm_appointments']) ? 1 : 0,
                'primary_color' => sanitize_hex_color($general['primary_color'] ?? '') ?: '#4f46e5',
                'secondary_color' => sanitize_hex_color($general['secondary_color'] ?? '') ?: '#06b6d4',
                'delete_data_on_uninstall' => !empty($general['delete_data_on_uninstall']) ? 1 : 0,
            ];
            update_option('appointpro_settings', $cleanGeneral);
            update_option('appointpro_delete_data_on_uninstall', $cleanGeneral['delete_data_on_uninstall']);
        }

        if (isset($params['payment'])) {
            $payment = (array) $params['payment'];
            $cleanPayment = [
                'offline_enabled' => !empty($payment['offline_enabled']) ? 1 : 0,
                'stripe_enabled' => !empty($payment['stripe_enabled']) ? 1 : 0,
                'stripe_publishable_key' => sanitize_text_field($payment['stripe_publishable_key'] ?? ''),
                'stripe_secret_key' => sanitize_text_field($payment['stripe_secret_key'] ?? ''),
                'stripe_webhook_secret' => sanitize_text_field($payment['stripe_webhook_secret'] ?? ''),
                'paypal_enabled' => !empty($payment['paypal_enabled']) ? 1 : 0,
                'paypal_sandbox' => !empty($payment['paypal_sandbox']) ? 1 : 0,
                'paypal_client_id' => sanitize_text_field($payment['paypal_client_id'] ?? ''),
                'paypal_secret' => sanitize_text_field($payment['paypal_secret'] ?? ''),
            ];
            update_option('appointpro_payment_settings', $cleanPayment);
        }

        return new WP_REST_Response(['success' => true, 'message' => __('Settings saved successfully', 'appointpro')], 200);
    }

    public function getCustomFields(): WP_REST_Response {
        global $wpdb;
        $table = $wpdb->prefix . 'appointpro_custom_fields';
        $fields = $wpdb->get_results("SELECT * FROM `{$table}` ORDER BY position ASC", ARRAY_A) ?: [];

        foreach ($fields as &$f) {
            $f['options'] = !empty($f['options']) ? json_decode($f['options'], true) : [];
        }

        return new WP_REST_Response(['success' => true, 'data' => $fields], 200);
    }

    public function saveCustomFields(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $table = $wpdb->prefix . 'appointpro_custom_fields';
        $fields = (array) ($request->get_json_params() ?? []);

        // Replace custom fields
        $wpdb->query("TRUNCATE TABLE `{$table}`");

        foreach ($fields as $idx => $f) {
            $wpdb->insert($table, [
                'label' => sanitize_text_field($f['label'] ?? ''),
                'field_type' => sanitize_text_field($f['field_type'] ?? 'text'),
                'options' => !empty($f['options']) ? json_encode($f['options']) : null,
                'is_required' => !empty($f['is_required']) ? 1 : 0,
                'position' => $idx,
            ]);
        }

        return new WP_REST_Response(['success' => true, 'message' => __('Custom fields updated', 'appointpro')], 200);
    }
}
