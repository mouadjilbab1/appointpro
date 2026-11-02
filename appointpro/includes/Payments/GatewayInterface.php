<?php
declare(strict_types=1);

namespace AppointPro\Payments;

/**
 * Contract for all AppointPro Payment Gateways.
 */
interface GatewayInterface {
    /**
     * Unique identifier for the gateway (e.g. 'stripe', 'paypal', 'offline', 'wc').
     *
     * @return string
     */
    public function getId(): string;

    /**
     * Human-readable gateway title.
     *
     * @return string
     */
    public function getTitle(): string;

    /**
     * Check if gateway is configured and enabled in settings.
     *
     * @return bool
     */
    public function isAvailable(): bool;

    /**
     * Process an incoming payment request.
     *
     * @param array<string, mixed> $appointment
     * @param float $amountToCharge
     * @param array<string, mixed> $customerData
     * @param array<string, mixed> $extraOptions
     * @return array{success: bool, transaction_id?: string, redirect_url?: string, client_secret?: string, message?: string}
     */
    public function processPayment(array $appointment, float $amountToCharge, array $customerData, array $extraOptions = []): array;

    /**
     * Handle asynchronous gateway webhook payload.
     *
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     * @return array{handled: bool, appointment_id?: int, status?: string, message?: string}
     */
    public function handleWebhook(array $payload, array $headers): array;
}
