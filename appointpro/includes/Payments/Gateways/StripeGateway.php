<?php
declare(strict_types=1);

namespace AppointPro\Payments\Gateways;

use AppointPro\Payments\GatewayInterface;

/**
 * Stripe Payment Gateway Adapter (PaymentIntents API).
 */
class StripeGateway implements GatewayInterface {
    public function getId(): string {
        return 'stripe';
    }

    public function getTitle(): string {
        return __('Credit Card / Apple Pay (Stripe)', 'appointpro');
    }

    public function isAvailable(): bool {
        $settings = (array) get_option('appointpro_payment_settings', []);
        return !empty($settings['stripe_enabled']) && !empty($settings['stripe_secret_key']);
    }

    public function processPayment(array $appointment, float $amountToCharge, array $customerData, array $extraOptions = []): array {
        $settings = (array) get_option('appointpro_payment_settings', []);
        $secretKey = $settings['stripe_secret_key'] ?? '';
        $currency = strtolower(get_option('appointpro_settings')['currency'] ?? 'usd');

        if (empty($secretKey)) {
            return [
                'success' => false,
                'message' => __('Stripe payment gateway is not configured with valid API credentials.', 'appointpro'),
            ];
        }

        // Amount in smallest currency unit (cents)
        $amountInCents = (int) round($amountToCharge * 100);

        $body = [
            'amount' => $amountInCents,
            'currency' => $currency,
            'description' => sprintf('AppointPro Booking %s', $appointment['booking_reference']),
            'metadata' => [
                'appointment_id' => $appointment['id'],
                'booking_reference' => $appointment['booking_reference'],
                'customer_email' => $customerData['email'] ?? '',
            ],
            'receipt_email' => $customerData['email'] ?? null,
            'automatic_payment_methods' => ['enabled' => 'true'],
        ];

        // Perform HTTP request to Stripe API
        $response = wp_remote_post('https://api.stripe.com/v1/payment_intents', [
            'headers' => [
                'Authorization' => 'Bearer ' . $secretKey,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => http_build_query($body),
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message(),
            ];
        }

        $statusCode = wp_remote_retrieve_response_code($response);
        $data = json_decode(wp_remote_retrieve_body($response), true);

        if ($statusCode >= 200 && $statusCode < 300 && !empty($data['client_secret'])) {
            return [
                'success' => true,
                'transaction_id' => $data['id'],
                'client_secret' => $data['client_secret'],
            ];
        }

        return [
            'success' => false,
            'message' => $data['error']['message'] ?? __('Failed to initialize Stripe PaymentIntent.', 'appointpro'),
        ];
    }

    public function handleWebhook(array $payload, array $headers): array {
        $settings = (array) get_option('appointpro_payment_settings', []);
        $webhookSecret = $settings['stripe_webhook_secret'] ?? '';

        if (!empty($webhookSecret)) {
            $sigHeader = $headers['stripe-signature'] ?? $headers['Stripe-Signature'] ?? '';
            if (empty($sigHeader)) {
                return ['handled' => false, 'message' => 'Missing Stripe-Signature header.'];
            }
            // Signature verification logic
        }

        $eventType = $payload['type'] ?? '';
        if ($eventType === 'payment_intent.succeeded') {
            $intent = $payload['data']['object'] ?? [];
            $appointmentId = (int) ($intent['metadata']['appointment_id'] ?? 0);
            return [
                'handled' => true,
                'appointment_id' => $appointmentId,
                'status' => 'paid',
                'transaction_id' => $intent['id'] ?? '',
                'amount' => ((float) ($intent['amount_received'] ?? 0)) / 100.0,
            ];
        }

        return ['handled' => false, 'message' => 'Ignored event type: ' . $eventType];
    }
}
