<?php
declare(strict_types=1);

namespace AppointPro\Payments\Gateways;

use AppointPro\Payments\GatewayInterface;

/**
 * PayPal REST API v2 Checkout Gateway Adapter.
 */
class PayPalGateway implements GatewayInterface {
    public function getId(): string {
        return 'paypal';
    }

    public function getTitle(): string {
        return __('PayPal', 'appointpro');
    }

    public function isAvailable(): bool {
        $settings = (array) get_option('appointpro_payment_settings', []);
        return !empty($settings['paypal_enabled']) && !empty($settings['paypal_client_id']) && !empty($settings['paypal_secret']);
    }

    protected function getApiBaseUrl(): string {
        $settings = (array) get_option('appointpro_payment_settings', []);
        $sandbox = !empty($settings['paypal_sandbox']);
        return $sandbox ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
    }

    protected function getAccessToken(): ?string {
        $settings = (array) get_option('appointpro_payment_settings', []);
        $clientId = $settings['paypal_client_id'] ?? '';
        $secret = $settings['paypal_secret'] ?? '';

        if (empty($clientId) || empty($secret)) {
            return null;
        }

        $url = $this->getApiBaseUrl() . '/v1/oauth2/token';
        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($clientId . ':' . $secret),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => 'grant_type=client_credentials',
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        return $data['access_token'] ?? null;
    }

    public function processPayment(array $appointment, float $amountToCharge, array $customerData, array $extraOptions = []): array {
        $token = $this->getAccessToken();
        if (!$token) {
            return [
                'success' => false,
                'message' => __('PayPal credentials are unconfigured or invalid.', 'appointpro'),
            ];
        }

        $currency = get_option('appointpro_settings')['currency'] ?? 'USD';

        $body = [
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'reference_id' => (string) $appointment['id'],
                    'custom_id' => $appointment['booking_reference'],
                    'description' => sprintf('AppointPro Booking %s', $appointment['booking_reference']),
                    'amount' => [
                        'currency_code' => $currency,
                        'value' => number_format($amountToCharge, 2, '.', ''),
                    ],
                ]
            ],
            'application_context' => [
                'brand_name' => get_bloginfo('name'),
                'landing_page' => 'NO_PREFERENCE',
                'user_action' => 'PAY_NOW',
                'return_url' => add_query_arg(['appointpro_paypal_return' => 1, 'ref' => $appointment['booking_reference']], site_url()),
                'cancel_url' => add_query_arg(['appointpro_paypal_cancel' => 1], site_url()),
            ],
        ];

        $url = $this->getApiBaseUrl() . '/v2/checkout/orders';
        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($body),
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'message' => $response->get_error_message()];
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!empty($data['id'])) {
            $approveUrl = '';
            foreach ($data['links'] ?? [] as $link) {
                if ($link['rel'] === 'approve') {
                    $approveUrl = $link['href'];
                    break;
                }
            }

            return [
                'success' => true,
                'transaction_id' => $data['id'],
                'redirect_url' => $approveUrl,
            ];
        }

        return [
            'success' => false,
            'message' => $data['message'] ?? __('Failed to initiate PayPal order.', 'appointpro'),
        ];
    }

    public function handleWebhook(array $payload, array $headers): array {
        $eventType = $payload['event_type'] ?? '';
        if ($eventType === 'PAYMENT.CAPTURE.COMPLETED') {
            $resource = $payload['resource'] ?? [];
            $customId = $resource['custom_id'] ?? '';
            return [
                'handled' => true,
                'status' => 'paid',
                'transaction_id' => $resource['id'] ?? '',
                'amount' => (float) ($resource['amount']['value'] ?? 0),
            ];
        }

        return ['handled' => false, 'message' => 'Ignored PayPal event'];
    }
}
