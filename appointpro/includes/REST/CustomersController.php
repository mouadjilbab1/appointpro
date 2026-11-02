<?php
declare(strict_types=1);

namespace AppointPro\REST;

use AppointPro\Customers\CustomerRepository;
use AppointPro\Permissions\PermissionsManager;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST Controller for Customer CRM and Profiles.
 */
class CustomersController {
    protected CustomerRepository $repo;

    public function __construct() {
        $this->repo = new CustomerRepository();
    }

    public function registerRoutes(): void {
        register_rest_route('appointpro/v1', '/customers', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getCustomers'],
                'permission_callback' => [$this, 'checkPermission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'saveCustomer'],
                'permission_callback' => [$this, 'checkPermission'],
            ]
        ]);

        register_rest_route('appointpro/v1', '/customers/(?P<id>\d+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'getCustomer'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);

        register_rest_route('appointpro/v1', '/customers/(?P<id>\d+)/appointments', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'getCustomerAppointments'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);
    }

    public function checkPermission(): bool {
        return current_user_can('read') && (
            current_user_can(PermissionsManager::CAP_MANAGE_CUSTOMERS) ||
            current_user_can('manage_options')
        );
    }

    public function getCustomers(WP_REST_Request $request): WP_REST_Response {
        $page = max(1, (int) $request->get_param('page'));
        $perPage = max(1, (int) ($request->get_param('per_page') ?: 20));
        $search = sanitize_text_field($request->get_param('search') ?? '');

        $result = $this->repo->getList($page, $perPage, $search);
        return new WP_REST_Response(['success' => true, 'data' => $result], 200);
    }

    public function getCustomer(WP_REST_Request $request): WP_REST_Response {
        $id = (int) $request->get_param('id');
        $customer = $this->repo->find($id);
        if (!$customer) {
            return new WP_REST_Response(['success' => false, 'message' => 'Customer not found'], 404);
        }
        return new WP_REST_Response(['success' => true, 'data' => $customer], 200);
    }

    public function saveCustomer(WP_REST_Request $request): WP_REST_Response {
        $params = $request->get_json_params() ?: $request->get_params();
        $id = !empty($params['id']) ? (int) $params['id'] : null;
        $customerId = $this->repo->save($params, $id);
        $saved = $this->repo->find($customerId);
        return new WP_REST_Response(['success' => true, 'data' => $saved], 200);
    }

    public function getCustomerAppointments(WP_REST_Request $request): WP_REST_Response {
        $id = (int) $request->get_param('id');
        $appts = $this->repo->getAppointments($id);
        return new WP_REST_Response(['success' => true, 'data' => $appts], 200);
    }
}
