<?php
declare(strict_types=1);

namespace AppointPro\Webhooks;

/**
 * Outgoing Webhook Event Dispatcher with HMAC SHA256 Signature and Delivery Logging.
 */
class WebhookDispatcher {
    /**
     * Dispatch an event to all subscribed active webhooks.
     *
     * @param string $event e.g. 'appointment.created', 'appointment.cancelled'
     * @param array<string, mixed> $payload
     * @return void
     */
    public function dispatch(string $event, array $payload): void {
        global $wpdb;
        $hookTable = $wpdb->prefix . 'appointpro_webhooks';
        $logTable = $wpdb->prefix . 'appointpro_webhook_logs';

        $webhooks = $wpdb->get_results(
            "SELECT * FROM `{$hookTable}` WHERE status = 'active'",
            ARRAY_A
        ) ?: [];

        $jsonPayload = json_encode([
            'event' => $event,
            'timestamp' => time(),
            'data' => $payload,
        ]);

        foreach ($webhooks as $hook) {
            $subscribedEvents = json_decode($hook['events'] ?? '[]', true) ?: [];
            if (!in_array($event, $subscribedEvents, true) && !in_array('*', $subscribedEvents, true)) {
                continue;
            }

            $secret = $hook['secret_key'];
            $signature = hash_hmac('sha256', $jsonPayload, $secret);

            $response = wp_remote_post($hook['target_url'], [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-AppointPro-Event' => $event,
                    'X-AppointPro-Signature' => $signature,
                    'User-Agent' => 'AppointPro-Webhook/' . APPOINTPRO_VERSION,
                ],
                'body' => $jsonPayload,
                'timeout' => 10,
            ]);

            $responseCode = is_wp_error($response) ? 0 : wp_remote_retrieve_response_code($response);
            $responseBody = is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_body($response);
            $status = ($responseCode >= 200 && $responseCode < 300) ? 'success' : 'failed';

            $wpdb->insert($logTable, [
                'webhook_id' => (int) $hook['id'],
                'event' => $event,
                'payload' => $jsonPayload,
                'response_code' => $responseCode,
                'response_body' => substr($responseBody, 0, 1000),
                'attempts' => 1,
                'status' => $status,
                'created_at' => gmdate('Y-m-d H:i:s'),
            ]);
        }
    }
}
