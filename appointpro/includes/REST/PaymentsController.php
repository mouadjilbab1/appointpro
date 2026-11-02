<?php
declare(strict_types=1);

namespace AppointPro\REST;

use AppointPro\Payments\PaymentManager;
use AppointPro\Permissions\PermissionsManager;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * REST Controller for Payment Processing, Gateways, and Inbound Webhooks.
 */
class PaymentsController {
    protected PaymentManager $paymentManager;

    public function __construct() {
        $this->paymentManager = new PaymentManager();
    }

    public function registerRoutes(): void {
        register_rest_route('appointpro/v1', '/payments/gateways', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'getAvailableGateways'],
            'permission_callback' => '__return_true', // Public for checkout wizard
        ]);

        register_rest_route('appointpro/v1', '/payments/charge', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'processPayment'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('appointpro/v1', '/payments/webhook/(?P<gateway>[a-zA-Z0-9_-]+)', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'handleWebhook'],
            'permission_callback' => '__return_true', // Webhooks are authenticated via HMAC signatures
        ]);

        register_rest_route('appointpro/v1', '/payments', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'getPaymentRecords'],
            'permission_callback' => [$this, 'checkAdminPermission'],
        ]);
    }

    public function checkAdminPermission(): bool {
        return current_user_can(PermissionsManager::CAP_VIEW_REPORTS) || current_user_can('manage_options');
    }

    public function getAvailableGateways(): WP_REST_Response {
        $gateways = $this->paymentManager->getAvailableGateways();
        $settings = (array) get_option('appointpro_payment_settings', []);

        $list = [];
        foreach ($gateways as $id => $gw) {
            $entry = [
                'id' => $id,
                'title' => $gw->getTitle(),
            ];
            if ($id === 'stripe') {
                $entry['publishable_key'] = $settings['stripe_publishable_key'] ?? '';
            }
            $list[] = $entry;
        }

        return new WP_REST_Response(['success' => true, 'data' => $list], 200);
    }

    public function processPayment(WP_REST_Request $request): WP_REST_Response {
        $params = $request->get_json_params() ?: $request->get_params();
        $appointmentId = (int) ($params['appointment_id'] ?? 0);
        $gatewayId = sanitize_text_field($params['gateway'] ?? 'offline');

        global $wpdb;
        $apptTable = $wpdb->prefix . 'appointpro_appointments';
        $appt = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$apptTable}` WHERE id = %d LIMIT 1", $appointmentId), ARRAY_A);

        if (!$appt) {
            return new WP_REST_Response(['success' => false, 'message' => 'Appointment not found'], 404);
        }

        $gateway = $this->paymentManager->getGateway($gatewayId);
        if (!$gateway) {
            return new WP_REST_Response(['success' => false, 'message' => 'Selected gateway is unavailable'], 400);
        }

        $amountToCharge = (float) ($appt['final_price'] - $appt['paid_amount']);
        $customerData = (array) ($params['customer'] ?? []);

        $result = $gateway->processPayment($appt, $amountToCharge, $customerData, $params);

        if (!empty($result['success']) && !empty($result['transaction_id'])) {
            $this->paymentManager->recordPayment(
                $appointmentId,
                $gatewayId,
                $amountToCharge,
                $result['transaction_id'],
                'completed',
                'full',
                $result
            );
        }

        return new WP_REST_Response($result, !empty($result['success']) ? 200 : 400);
    }

    public function handleWebhook(WP_REST_Request $request): WP_REST_Response {
        $gatewayId = $request->get_param('gateway');
        $gateway = $this->paymentManager->getGateway($gatewayId);

        if (!$gateway) {
            return new WP_REST_Response(['handled' => false, 'message' => 'Unknown gateway'], 404);
        }

        $headers = $request->get_headers();
        $payload = $request->get_json_params() ?: $request->get_params();

        $result = $gateway->handleWebhook($payload, $headers);

        if (!empty($result['handled']) && !empty($result['appointment_id']) && ($result['status'] ?? '') === 'paid') {
            $this->paymentManager->recordPayment(
                (int) $result['appointment_id'],
                $gatewayId,
                (float) ($result['amount'] ?? 0),
                $result['transaction_id'] ?? '',
                'completed',
                'full',
                $payload
            );
        }

        return new WP_REST_Response($result, 200);
    }

    public function getPaymentRecords(WP_REST_Request $request): WP_REST_Response {
        global $wpdb;
        $table = $wpdb->prefix . 'appointpro_payments';
        $apptTable = $wpdb->prefix . 'appointpro_appointments';

        $records = $wpdb->get_results(
            "SELECT p.*, a.booking_reference
             FROM `{$table}` p
             LEFT JOIN `{$apptTable}` a ON p.appointment_id = a.id
             ORDER BY p.created_at DESC LIMIT 100",
            ARRAY_A
        ) ?: [];

        return new WP_REST_Response(['success' => true, 'data' => $records], 200);
    }
}
