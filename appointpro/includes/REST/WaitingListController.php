<?php
declare(strict_types=1);

namespace AppointPro\REST;

use AppointPro\Customers\CustomerRepository;
use AppointPro\Permissions\PermissionsManager;
use AppointPro\WaitingList\WaitingListManager;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST Controller for Customer Waiting List.
 */
class WaitingListController {
    public function registerRoutes(): void {
        register_rest_route('appointpro/v1', '/waiting-list', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getList'],
                'permission_callback' => [$this, 'checkAdminPermission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'joinWaitingList'],
                'permission_callback' => '__return_true', // Public for customers
            ]
        ]);

        register_rest_route('appointpro/v1', '/waiting-list/(?P<id>\d+)', [
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => [$this, 'removeEntry'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);
    }

    public function checkAdminPermission(): bool {
        return current_user_can(PermissionsManager::CAP_MANAGE_APPOINTMENTS) || current_user_can('manage_options');
    }

    public function getList(): WP_REST_Response {
        global $wpdb;
        $waitTable = $wpdb->prefix . 'appointpro_waiting_list';
        $svcTable = $wpdb->prefix . 'appointpro_services';
        $custTable = $wpdb->prefix . 'appointpro_customers';

        $records = $wpdb->get_results(
            "SELECT w.*, s.name AS service_name, c.first_name, c.last_name, c.email, c.phone
             FROM `{$waitTable}` w
             LEFT JOIN `{$svcTable}` s ON w.service_id = s.id
             LEFT JOIN `{$custTable}` c ON w.customer_id = c.id
             ORDER BY w.created_at DESC LIMIT 50",
            ARRAY_A
        ) ?: [];

        return new WP_REST_Response(['success' => true, 'data' => $records], 200);
    }

    public function joinWaitingList(WP_REST_Request $request): WP_REST_Response {
        $params = $request->get_json_params() ?: $request->get_params();
        $serviceId = (int) ($params['service_id'] ?? 0);
        $desiredDate = sanitize_text_field($params['desired_date'] ?? date('Y-m-d'));
        $customerData = (array) ($params['customer'] ?? []);

        $custRepo = new CustomerRepository();
        $customerId = $custRepo->findOrCreate($customerData, get_current_user_id() ?: null);

        $mgr = new WaitingListManager();
        $entryId = $mgr->addToWaitingList($serviceId, $customerId, $desiredDate);

        return new WP_REST_Response([
            'success' => true,
            'id' => $entryId,
            'message' => __('You have been placed on the waiting list. We will notify you if a slot frees up!', 'appointpro'),
        ], 200);
    }

    public function removeEntry(WP_REST_Request $request): WP_REST_Response {
        $id = (int) $request->get_param('id');
        global $wpdb;
        $table = $wpdb->prefix . 'appointpro_waiting_list';
        $success = (bool) $wpdb->delete($table, ['id' => $id]);
        return new WP_REST_Response(['success' => $success], 200);
    }
}
