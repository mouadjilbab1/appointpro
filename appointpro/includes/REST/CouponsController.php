<?php
declare(strict_types=1);

namespace AppointPro\REST;

use AppointPro\Coupons\CouponService;
use AppointPro\Permissions\PermissionsManager;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST Controller for Coupons Management and Validation.
 */
class CouponsController {
    public function registerRoutes(): void {
        register_rest_route('appointpro/v1', '/coupons', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getCoupons'],
                'permission_callback' => [$this, 'checkAdminPermission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'saveCoupon'],
                'permission_callback' => [$this, 'checkAdminPermission'],
            ]
        ]);

        register_rest_route('appointpro/v1', '/coupons/validate', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'validateCoupon'],
            'permission_callback' => '__return_true', // Public for checkout wizard
        ]);

        register_rest_route('appointpro/v1', '/coupons/(?P<id>\d+)', [
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => [$this, 'deleteCoupon'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);
    }

    public function checkAdminPermission(): bool {
        return current_user_can(PermissionsManager::CAP_MANAGE_SETTINGS) || current_user_can('manage_options');
    }

    public function getCoupons(): WP_REST_Response {
        global $wpdb;
        $table = $wpdb->prefix . 'appointpro_coupons';
        $coupons = $wpdb->get_results("SELECT * FROM `{$table}` ORDER BY created_at DESC", ARRAY_A) ?: [];
        return new WP_REST_Response(['success' => true, 'data' => $coupons], 200);
    }

    public function saveCoupon(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $table = $wpdb->prefix . 'appointpro_coupons';
        $params = $request->get_json_params() ?: $request->get_params();
        $id = !empty($params['id']) ? (int) $params['id'] : null;

        $record = [
            'code' => strtoupper(sanitize_text_field($params['code'] ?? '')),
            'discount_type' => in_array($params['discount_type'] ?? '', ['fixed', 'percentage'], true) ? $params['discount_type'] : 'fixed',
            'discount_value' => (float) ($params['discount_value'] ?? 0.0),
            'min_order_amount' => (float) ($params['min_order_amount'] ?? 0.0),
            'usage_limit_total' => !empty($params['usage_limit_total']) ? (int) $params['usage_limit_total'] : null,
            'usage_limit_per_customer' => max(1, (int) ($params['usage_limit_per_customer'] ?? 1)),
            'expires_at' => !empty($params['expires_at']) ? sanitize_text_field($params['expires_at']) : null,
            'status' => in_array($params['status'] ?? '', ['active', 'inactive'], true) ? $params['status'] : 'active',
        ];

        if ($id && $id > 0) {
            $wpdb->update($table, $record, ['id' => $id]);
            $couponId = $id;
        } else {
            $record['created_at'] = gmdate('Y-m-d H:i:s');
            $wpdb->insert($table, $record);
            $couponId = (int) $wpdb->insert_id;
        }

        $saved = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$table}` WHERE id = %d", $couponId), ARRAY_A);
        return new WP_REST_Response(['success' => true, 'data' => $saved], 200);
    }

    public function validateCoupon(WP_REST_Request $request): WP_REST_Response {
        $params = $request->get_json_params() ?: $request->get_params();
        $code = sanitize_text_field($params['code'] ?? '');
        $subtotal = (float) ($params['subtotal'] ?? 0.0);
        $serviceId = (int) ($params['service_id'] ?? 0);

        $couponService = new CouponService();
        $res = $couponService->validateAndCalculateDiscount($code, $subtotal, $serviceId);

        return new WP_REST_Response($res, 200);
    }

    public function deleteCoupon(WP_REST_Request $request): WP_REST_Response {
        $id = (int) $request->get_param('id');
        global $wpdb;
        $table = $wpdb->prefix . 'appointpro_coupons';
        $success = (bool) $wpdb->delete($table, ['id' => $id]);
        return new WP_REST_Response(['success' => $success], 200);
    }
}
