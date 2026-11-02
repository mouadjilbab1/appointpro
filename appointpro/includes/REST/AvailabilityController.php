<?php
declare(strict_types=1);

namespace AppointPro\REST;

use AppointPro\Availability\AvailabilityEngine;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST Controller for Slot Availability and Temporary Reservation Locks.
 */
class AvailabilityController {
    protected AvailabilityEngine $engine;

    public function __construct() {
        $this->engine = new AvailabilityEngine();
    }

    public function registerRoutes(): void {
        register_rest_route('appointpro/v1', '/availability', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'getSlots'],
            'permission_callback' => '__return_true', // Public for booking wizard
        ]);

        register_rest_route('appointpro/v1', '/availability/lock', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'lockSlot'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('appointpro/v1', '/availability/release', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'releaseLock'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function getSlots(WP_REST_Request $request): WP_REST_Response {
        $serviceId = (int) $request->get_param('service_id');
        $staffId = $request->get_param('staff_id') ? (int) $request->get_param('staff_id') : null;
        $date = sanitize_text_field($request->get_param('date') ?? date('Y-m-d'));
        $locationId = $request->get_param('location_id') ? (int) $request->get_param('location_id') : null;

        if ($serviceId <= 0) {
            return new WP_REST_Response(['success' => false, 'message' => 'Missing service_id'], 400);
        }

        $slots = $this->engine->getAvailableSlots($serviceId, $staffId, $date, $locationId);

        return new WP_REST_Response([
            'success' => true,
            'date' => $date,
            'data' => $slots,
        ], 200);
    }

    public function lockSlot(WP_REST_Request $request): WP_REST_Response {
        $params = $request->get_json_params() ?: $request->get_params();
        $staffId = (int) ($params['staff_id'] ?? 0);
        $utcStart = sanitize_text_field($params['start_time'] ?? '');
        $utcEnd = sanitize_text_field($params['end_time'] ?? '');
        $sessionToken = sanitize_text_field($params['session_token'] ?? '');

        if (!$staffId || !$utcStart || !$utcEnd || !$sessionToken) {
            return new WP_REST_Response(['success' => false, 'message' => 'Missing parameters for slot reservation'], 400);
        }

        $locked = $this->engine->reserveSlotLock($staffId, $utcStart, $utcEnd, $sessionToken, 10);

        return new WP_REST_Response([
            'success' => $locked,
            'message' => $locked ? 'Slot temporarily reserved' : 'Slot no longer available',
        ], $locked ? 200 : 409);
    }

    public function releaseLock(WP_REST_Request $request): WP_REST_Response {
        $params = $request->get_json_params() ?: $request->get_params();
        $sessionToken = sanitize_text_field($params['session_token'] ?? '');
        if ($sessionToken) {
            $this->engine->releaseSlotLock($sessionToken);
        }
        return new WP_REST_Response(['success' => true], 200);
    }
}
