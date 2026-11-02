<?php
declare(strict_types=1);

namespace AppointPro\REST;

use AppointPro\Permissions\PermissionsManager;
use AppointPro\Staff\StaffRepository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST Controller for Staff, Working Hours, Schedules, and Special Days.
 */
class StaffController {
    protected StaffRepository $repo;

    public function __construct() {
        $this->repo = new StaffRepository();
    }

    public function registerRoutes(): void {
        register_rest_route('appointpro/v1', '/staff', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getStaffList'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'saveStaff'],
                'permission_callback' => [$this, 'checkAdminPermission'],
            ]
        ]);

        register_rest_route('appointpro/v1', '/staff/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getStaffMember'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'deleteStaff'],
                'permission_callback' => [$this, 'checkAdminPermission'],
            ]
        ]);

        register_rest_route('appointpro/v1', '/staff/(?P<id>\d+)/schedule', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getSchedule'],
                'permission_callback' => [$this, 'checkSchedulePermission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'saveSchedule'],
                'permission_callback' => [$this, 'checkSchedulePermission'],
            ]
        ]);

        register_rest_route('appointpro/v1', '/staff/special-days', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'saveSpecialDay'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);
    }

    public function checkAdminPermission(): bool {
        return current_user_can(PermissionsManager::CAP_MANAGE_STAFF) || current_user_can('manage_options');
    }

    public function checkSchedulePermission(WP_REST_Request $request): bool {
        if ($this->checkAdminPermission()) {
            return true;
        }

        $staffId = (int) $request->get_param('id');
        $currentUserId = get_current_user_id();
        if (!$currentUserId) {
            return false;
        }

        global $wpdb;
        $staffTable = $wpdb->prefix . 'appointpro_staff';
        $assignedStaffId = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM `{$staffTable}` WHERE wp_user_id = %d LIMIT 1",
            $currentUserId
        ));

        return $assignedStaffId === $staffId && current_user_can(PermissionsManager::CAP_EDIT_OWN_SCHEDULE);
    }

    public function getStaffList(): WP_REST_Response {
        $staff = $this->repo->getAll(true);
        return new WP_REST_Response(['success' => true, 'data' => $staff], 200);
    }

    public function getStaffMember(WP_REST_Request $request): WP_REST_Response {
        $id = (int) $request->get_param('id');
        $staff = $this->repo->find($id);
        if (!$staff) {
            return new WP_REST_Response(['success' => false, 'message' => 'Staff not found'], 404);
        }
        return new WP_REST_Response(['success' => true, 'data' => $staff], 200);
    }

    public function saveStaff(WP_REST_Request $request): WP_REST_Response {
        $params = $request->get_json_params() ?: $request->get_params();
        $id = !empty($params['id']) ? (int) $params['id'] : null;

        $staffId = $this->repo->save($params, $id);
        $saved = $this->repo->find($staffId);

        return new WP_REST_Response(['success' => true, 'data' => $saved], 200);
    }

    public function deleteStaff(WP_REST_Request $request): WP_REST_Response {
        $id = (int) $request->get_param('id');
        $success = $this->repo->delete($id);
        return new WP_REST_Response(['success' => $success], 200);
    }

    public function getSchedule(WP_REST_Request $request): WP_REST_Response {
        $id = (int) $request->get_param('id');
        $schedule = $this->repo->getWeeklySchedule($id);
        return new WP_REST_Response(['success' => true, 'data' => $schedule], 200);
    }

    public function saveSchedule(WP_REST_Request $request): WP_REST_Response {
        $id = (int) $request->get_param('id');
        $days = (array) ($request->get_json_params() ?? []);
        $this->repo->saveWeeklySchedule($id, $days);
        return new WP_REST_Response(['success' => true, 'message' => __('Schedule updated', 'appointpro')], 200);
    }

    public function saveSpecialDay(WP_REST_Request $request): WP_REST_Response {
        $params = $request->get_json_params() ?: $request->get_params();
        $id = $this->repo->saveSpecialDay($params);
        return new WP_REST_Response(['success' => true, 'id' => $id], 200);
    }
}
