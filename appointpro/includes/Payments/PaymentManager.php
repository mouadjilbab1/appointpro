<?php
declare(strict_types=1);

namespace AppointPro\Payments;

use AppointPro\Notifications\NotificationDispatcher;
use AppointPro\Payments\Gateways\OfflineGateway;
use AppointPro\Payments\Gateways\PayPalGateway;
use AppointPro\Payments\Gateways\StripeGateway;

/**
 * Payment Manager: Orchestrates Gateways, Transaction Records, and Payment Statuses.
 */
class PaymentManager {
    /**
     * @var array<string, GatewayInterface>
     */
    protected array $gateways = [];

    public function __construct() {
        $this->registerGateway(new OfflineGateway());
        $this->registerGateway(new StripeGateway());
        $this->registerGateway(new PayPalGateway());
    }

    public function registerGateway(GatewayInterface $gateway): void {
        $this->gateways[$gateway->getId()] = $gateway;
    }

    public function getGateway(string $id): ?GatewayInterface {
        return $this->gateways[$id] ?? null;
    }

    /**
     * Get all active & available gateways.
     *
     * @return array<string, GatewayInterface>
     */
    public function getAvailableGateways(): array {
        $available = [];
        foreach ($this->gateways as $id => $gateway) {
            if ($gateway->isAvailable()) {
                $available[$id] = $gateway;
            }
        }
        return $available;
    }

    /**
     * Record payment transaction and update appointment financial status.
     *
     * @param int $appointmentId
     * @param string $gatewayId
     * @param float $amount
     * @param string $transactionId
     * @param string $status 'completed', 'pending', 'failed', 'refunded'
     * @param string $type 'full', 'deposit', 'remaining'
     * @param array<string, mixed>|null $rawPayload
     * @return int Payment record ID
     */
    public function recordPayment(
        int $appointmentId,
        string $gatewayId,
        float $amount,
        string $transactionId,
        string $status = 'completed',
        string $type = 'full',
        ?array $rawPayload = null
    ): int {
        global $wpdb;
        $payTable = $wpdb->prefix . 'appointpro_payments';
        $apptTable = $wpdb->prefix . 'appointpro_appointments';
        $now = gmdate('Y-m-d H:i:s');
        $currency = get_option('appointpro_settings')['currency'] ?? 'USD';

        $wpdb->insert($payTable, [
            'appointment_id' => $appointmentId,
            'gateway' => $gatewayId,
            'transaction_id' => $transactionId,
            'amount' => $amount,
            'currency' => $currency,
            'type' => $type,
            'status' => $status,
            'gateway_response' => $rawPayload ? json_encode($rawPayload) : null,
            'created_at' => $now,
        ]);

        $paymentId = (int) $wpdb->insert_id;

        if ($status === 'completed') {
            // Update appointment total paid amount
            $appt = $wpdb->get_row($wpdb->prepare("SELECT final_price, paid_amount FROM `{$apptTable}` WHERE id = %d", $appointmentId), ARRAY_A);
            if ($appt) {
                $newPaid = (float) $appt['paid_amount'] + $amount;
                $finalPrice = (float) $appt['final_price'];

                $payStatus = ($newPaid >= $finalPrice && $finalPrice > 0) ? 'paid' : 'partial';

                $wpdb->update($apptTable, [
                    'paid_amount' => $newPaid,
                    'payment_status' => $payStatus,
                    'status' => 'confirmed', // Confirm upon successful payment
                    'updated_at' => $now,
                ], ['id' => $appointmentId]);
            }

            // Queue notification
            $dispatcher = new NotificationDispatcher();
            $dispatcher->queueEventNotification('payment_completed', $appointmentId);
        }

        return $paymentId;
    }
}
