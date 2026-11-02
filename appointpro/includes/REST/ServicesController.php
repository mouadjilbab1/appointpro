<?php
declare(strict_types=1);

namespace AppointPro\REST;

use AppointPro\Permissions\PermissionsManager;
use AppointPro\Services\ServiceRepository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST Controller for Categories and Services Management.
 */
class ServicesController {
    protected ServiceRepository $repo;

    public function __construct() {
        $this->repo = new ServiceRepository();
    }

    public function registerRoutes(): void {
        register_rest_route('appointpro/v1', '/services', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getServices'],
                'permission_callback' => '__return_true', // Public for booking wizard
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'saveService'],
                'permission_callback' => [$this, 'checkAdminPermission'],
            ]
        ]);

        register_rest_route('appointpro/v1', '/services/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'deleteService'],
                'permission_callback' => [$this, 'checkAdminPermission'],
            ]
        ]);

        register_rest_route('appointpro/v1', '/categories', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getCategories'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'saveCategory'],
                'permission_callback' => [$this, 'checkAdminPermission'],
            ]
        ]);

        register_rest_route('appointpro/v1', '/categories/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'deleteCategory'],
                'permission_callback' => [$this, 'checkAdminPermission'],
            ]
        ]);
    }

    public function checkAdminPermission(): bool {
        return current_user_can(PermissionsManager::CAP_MANAGE_SERVICES) || current_user_can('manage_options');
    }

    public function getServices(): WP_REST_Response {
        $categories = $this->repo->getCategoriesWithServices();
        return new WP_REST_Response(['success' => true, 'data' => $categories], 200);
    }

    public function saveService(WP_REST_Request $request): WP_REST_Response {
        $params = $request->get_json_params() ?: $request->get_params();
        $id = !empty($params['id']) ? (int) $params['id'] : null;

        $serviceId = $this->repo->save($params, $id);
        $saved = $this->repo->find($serviceId);

        return new WP_REST_Response(['success' => true, 'data' => $saved], 200);
    }

    public function deleteService(WP_REST_Request $request): WP_REST_Response {
        $id = (int) $request->get_param('id');
        $success = $this->repo->delete($id);
        return new WP_REST_Response(['success' => $success], 200);
    }

    public function getCategories(): WP_REST_Response {
        global $wpdb;
        $table = $wpdb->prefix . 'appointpro_categories';
        $cats = $wpdb->get_results("SELECT * FROM `{$table}` ORDER BY position ASC, name ASC", ARRAY_A);
        return new WP_REST_Response(['success' => true, 'data' => $cats ?: []], 200);
    }

    public function saveCategory(WP_REST_Request $request): WP_REST_Response {
        $params = $request->get_json_params() ?: $request->get_params();
        $id = !empty($params['id']) ? (int) $params['id'] : null;

        $catId = $this->repo->saveCategory($params, $id);
        return new WP_REST_Response(['success' => true, 'category_id' => $catId], 200);
    }

    public function deleteCategory(WP_REST_Request $request): WP_REST_Response {
        $id = (int) $request->get_param('id');
        $success = $this->repo->deleteCategory($id);
        return new WP_REST_Response(['success' => $success], 200);
    }
}
