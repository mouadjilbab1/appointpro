<?php
declare(strict_types=1);

namespace AppointPro\REST;

use AppointPro\Permissions\PermissionsManager;
use AppointPro\Resources\ResourceRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST Controller for Shared Equipment and Rooms Management.
 */
class ResourcesController {
    protected ResourceRepository $repo;

    public function __construct() {
        $this->repo = new ResourceRepository();
    }

    public function registerRoutes(): void {
        register_rest_route('appointpro/v1', '/resources', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getResources'],
                'permission_callback' => [$this, 'checkAdminPermission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'saveResource'],
                'permission_callback' => [$this, 'checkAdminPermission'],
            ]
        ]);

        register_rest_route('appointpro/v1', '/resources/(?P<id>\d+)', [
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => [$this, 'deleteResource'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);
    }

    public function checkAdminPermission(): bool {
        return current_user_can(PermissionsManager::CAP_MANAGE_SETTINGS) || current_user_can('manage_options');
    }

    public function getResources(): WP_REST_Response {
        $resources = $this->repo->getAll(true);
        return new WP_REST_Response(['success' => true, 'data' => $resources], 200);
    }

    public function saveResource(WP_REST_Request $request): WP_REST_Response {
        $params = $request->get_json_params() ?: $request->get_params();
        $id = !empty($params['id']) ? (int) $params['id'] : null;
        $resId = $this->repo->save($params, $id);
        $saved = $this->repo->find($resId);
        return new WP_REST_Response(['success' => true, 'data' => $saved], 200);
    }

    public function deleteResource(WP_REST_Request $request): WP_REST_Response {
        $id = (int) $request->get_param('id');
        $success = $this->repo->delete($id);
        return new WP_REST_Response(['success' => $success], 200);
    }
}
