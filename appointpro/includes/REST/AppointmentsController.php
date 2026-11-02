<?php
declare(strict_types=1);

namespace AppointPro\REST;

use AppointPro\Booking\BookingEngine;
use AppointPro\Calendar\CalendarManager;
use AppointPro\Permissions\PermissionsManager;
use AppointPro\Security\Security;
use Exception;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST Controller for Appointments CRUD, Booking Wizard Submission, Rescheduling, and Cancellation.
 */
class AppointmentsController {
    protected BookingEngine $bookingEngine;

    public function __construct() {
        $this->bookingEngine = new BookingEngine();
    }

    public function registerRoutes(): void {
        register_rest_route('appointpro/v1', '/appointments', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'getAppointments'],
                'permission_callback' => [$this, 'checkReadPermission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'createAppointment'],
                'permission_callback' => '__return_true', // Public for customer booking wizard
            ]
        ]);

        register_rest_route('appointpro/v1', '/appointments/calculate', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'calculatePricing'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('appointpro/v1', '/appointments/(?P<id>\d+)/status', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'updateStatus'],
            'permission_callback' => [$this, 'checkManagePermission'],
        ]);

        register_rest_route('appointpro/v1', '/appointments/(?P<id>\d+)/reschedule', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'reschedule'],
            'permission_callback' => [$this, 'checkAppointmentOwnerOrStaff'],
        ]);

        register_rest_route('appointpro/v1', '/appointments/(?P<id>\d+)/cancel', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'cancel'],
            'permission_callback' => [$this, 'checkAppointmentOwnerOrStaff'],
        ]);
    }

    public function checkReadPermission(): bool {
        return current_user_can('read') && (
            current_user_can(PermissionsManager::CAP_MANAGE_APPOINTMENTS) ||
            current_user_can(PermissionsManager::CAP_VIEW_OWN_APPOINTMENTS)
        );
    }

    public function checkManagePermission(): bool {
        return current_user_can(PermissionsManager::CAP_MANAGE_APPOINTMENTS) || current_user_can('manage_options');
    }

    public function checkAppointmentOwnerOrStaff(WP_REST_Request $request): bool {
        if ($this->checkManagePermission()) {
            return true;
        }

        $id = (int) $request->get_param('id');
        global $wpdb;
        $apptTable = $wpdb->prefix . 'appointpro_appointments';
        $appt = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$apptTable}` WHERE id = %d LIMIT 1", $id), ARRAY_A);
        if (!$appt) {
            return false;
        }

        // Check if current user is the assigned staff
        if (Security::canAccessStaffResource((int) $appt['staff_id'])) {
            return true;
        }

        // Check if customer owner
        $currentUserId = get_current_user_id();
        if ($currentUserId) {
            $custTable = $wpdb->prefix . 'appointpro_customers';
            $acTable = $wpdb->prefix . 'appointpro_appointment_customers';
            $isOwner = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM `{$acTable}` ac
                 INNER JOIN `{$custTable}` c ON ac.customer_id = c.id
                 WHERE ac.appointment_id = %d AND c.wp_user_id = %d",
                $id,
                $currentUserId
            ));
            return $isOwner > 0;
        }

        return false;
    }

    public function getAppointments(WP_REST_Request $request): WP_REST_Response {
        $startDate = sanitize_text_field($request->get_param('start_date') ?? date('Y-m-01'));
        $endDate = sanitize_text_field($request->get_param('end_date') ?? date('Y-m-t'));
        $staffId = $request->get_param('staff_id') ? (int) $request->get_param('staff_id') : null;
        $serviceId = $request->get_param('service_id') ? (int) $request->get_param('service_id') : null;
        $locationId = $request->get_param('location_id') ? (int) $request->get_param('location_id') : null;

        $calendar = new CalendarManager();
        $events = $calendar->getEvents($startDate, $endDate, $staffId, $serviceId, $locationId);

        return new WP_REST_Response(['success' => true, 'data' => $events], 200);
    }

    public function calculatePricing(WP_REST_Request $request): WP_REST_Response {
        $params = $request->get_json_params() ?: $request->get_params();
        $serviceId = (int) ($params['service_id'] ?? 0);
        $staffId = (int) ($params['staff_id'] ?? 0);
        $extras = (array) ($params['extras'] ?? []);
        $people = max(1, (int) ($params['number_of_persons'] ?? 1));
        $coupon = !empty($params['coupon_code']) ? sanitize_text_field($params['coupon_code']) : null;

        try {
            $pricing = $this->bookingEngine->calculatePricing($serviceId, $staffId, $extras, $people, $coupon);
            return new WP_REST_Response(['success' => true, 'data' => $pricing], 200);
        } catch (Exception $e) {
            return new WP_REST_Response(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function createAppointment(WP_REST_Request $request): WP_REST_Response {
        // Enforce rate limiting
        if (!Security::checkRateLimit('create_appointment', 15, 60)) {
            return new WP_REST_Response(['success' => false, 'message' => __('Rate limit exceeded. Please wait a moment before trying again.', 'appointpro')], 429);
        }

        $params = $request->get_json_params() ?: $request->get_params();

        try {
            $result = $this->bookingEngine->createAppointment($params);
            return new WP_REST_Response($result, 200);
        } catch (Exception $e) {
            return new WP_REST_Response(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function updateStatus(WP_REST_Request $request): WP_REST_Response {
        $id = (int) $request->get_param('id');
        $params = $request->get_json_params() ?: $request->get_params();
        $status = sanitize_text_field($params['status'] ?? '');

        if (!in_array($status, ['confirmed', 'pending', 'cancelled', 'rejected', 'completed', 'no-show'], true)) {
            return new WP_REST_Response(['success' => false, 'message' => 'Invalid status'], 400);
        }

        global $wpdb;
        $apptTable = $wpdb->prefix . 'appointpro_appointments';
        $wpdb->update($apptTable, ['status' => $status, 'updated_at' => gmdate('Y-m-d H:i:s')], ['id' => $id]);

        return new WP_REST_Response(['success' => true, 'status' => $status], 200);
    }

    public function reschedule(WP_REST_Request $request): WP_REST_Response {
        $id = (int) $request->get_param('id');
        $params = $request->get_json_params() ?: $request->get_params();
        $newStart = sanitize_text_field($params['start_time'] ?? '');
        $newEnd = sanitize_text_field($params['end_time'] ?? '');
        $newStaff = !empty($params['staff_id']) ? (int) $params['staff_id'] : null;

        try {
            $this->bookingEngine->reschedule($id, $newStart, $newEnd, $newStaff);
            return new WP_REST_Response(['success' => true, 'message' => __('Appointment rescheduled successfully', 'appointpro')], 200);
        } catch (Exception $e) {
            return new WP_REST_Response(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function cancel(WP_REST_Request $request): WP_REST_Response {
        $id = (int) $request->get_param('id');
        $params = $request->get_json_params() ?: $request->get_params();
        $reason = sanitize_text_field($params['reason'] ?? '');

        $success = $this->bookingEngine->cancel($id, $reason);
        return new WP_REST_Response(['success' => $success], 200);
    }
}
