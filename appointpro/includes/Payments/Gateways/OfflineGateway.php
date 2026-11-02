<?php
declare(strict_types=1);

namespace AppointPro\Payments\Gateways;

use AppointPro\Payments\GatewayInterface;

/**
 * Offline Payments: Cash on Arrival and Bank Wire Transfer.
 */
class OfflineGateway implements GatewayInterface {
    public function getId(): string {
        return 'offline';
    }

    public function getTitle(): string {
        return __('Pay Locally / Bank Transfer', 'appointpro');
    }

    public function isAvailable(): bool {
        $settings = (array) get_option('appointpro_payment_settings', []);
        return !empty($settings['offline_enabled']);
    }

    public function processPayment(array $appointment, float $amountToCharge, array $customerData, array $extraOptions = []): array {
        return [
            'success' => true,
            'transaction_id' => 'OFFLINE-' . strtoupper(wp_generate_password(8, false)),
            'message' => __('Appointment reserved. Payment will be collected in person or via bank transfer.', 'appointpro'),
        ];
    }

    public function handleWebhook(array $payload, array $headers): array {
        return ['handled' => false, 'message' => 'Webhooks not applicable for offline payments.'];
    }
}
