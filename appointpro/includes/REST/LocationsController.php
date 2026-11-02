<?php
declare(strict_types=1);

namespace AppointPro\REST;

use AppointPro\Locations\LocationRepository;
use AppointPro\Permissions\PermissionsManager;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST Controller for Multi-location Business Branches.
 */
class LocationsController {
    protected LocationRepository $repo;

    public function __construct() {
        $this->repo = new LocationRepository();
    }

    public function registerRoutes(): void {
        register_rest_route('appointpro/v1', '/locations', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getLocations'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'saveLocation'],
                'permission_callback' => [$this, 'checkAdminPermission'],
            ]
        ]);

        register_rest_route('appointpro/v1', '/locations/(?P<id>\d+)', [
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => [$this, 'deleteLocation'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);
    }

    public function checkAdminPermission(): bool {
        return current_user_can(PermissionsManager::CAP_MANAGE_SETTINGS) || current_user_can('manage_options');
    }

    public function getLocations(): WP_REST_Response {
        $locations = $this->repo->getAll(true);
        return new WP_REST_Response(['success' => true, 'data' => $locations], 200);
    }

    public function saveLocation(WP_REST_Request $request): WP_REST_Response {
        $params = $request->get_json_params() ?: $request->get_params();
        $id = !empty($params['id']) ? (int) $params['id'] : null;
        $locId = $this->repo->save($params, $id);
        $saved = $this->repo->find($locId);
        return new WP_REST_Response(['success' => true, 'data' => $saved], 200);
    }

    public function deleteLocation(WP_REST_Request $request): WP_REST_Response {
        $id = (int) $request->get_param('id');
        $success = $this->repo->delete($id);
        return new WP_REST_Response(['success' => $success], 200);
    }
}
