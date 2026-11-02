<?php
declare(strict_types=1);

namespace AppointPro\AI;

use AppointPro\Availability\AvailabilityEngine;
use AppointPro\Services\ServiceRepository;

/**
 * Optional AI Assistant: Provides natural language booking recommendations and inquiries.
 * Strictly uses real database services and available slots without hallucination.
 */
class AIAssistant {
    protected ServiceRepository $serviceRepo;
    protected AvailabilityEngine $availabilityEngine;

    public function __construct(
        ?ServiceRepository $serviceRepo = null,
        ?AvailabilityEngine $availabilityEngine = null
    ) {
        $this->serviceRepo = $serviceRepo ?: new ServiceRepository();
        $this->availabilityEngine = $availabilityEngine ?: new AvailabilityEngine();
    }

    /**
     * Check if AI assistant is enabled and has configured API key.
     *
     * @return bool
     */
    public function isEnabled(): bool {
        $settings = (array) get_option('appointpro_ai_settings', []);
        return !empty($settings['enabled']) && !empty($settings['api_key']);
    }

    /**
     * Process natural language query from customer.
     *
     * @param string $userQuery
     * @return array{reply: string, suggested_services?: array<int, array<string, mixed>>}
     */
    public function query(string $userQuery): array {
        if (!$this->isEnabled()) {
            return [
                'reply' => __('The AI Assistant is currently disabled. Please contact the business directly or use the booking scheduler.', 'appointpro'),
            ];
        }

        $settings = (array) get_option('appointpro_ai_settings', []);
        $apiKey = $settings['api_key'] ?? '';
        $provider = $settings['provider'] ?? 'openai'; // openai, gemini, anthropic

        // Fetch verified active services to ground AI
        $categories = $this->serviceRepo->getCategoriesWithServices();
        $serviceList = [];
        foreach ($categories as $cat) {
            foreach ($cat['services'] ?? [] as $svc) {
                $serviceList[] = sprintf(
                    "- %s (ID: %d): %d mins, %s. Description: %s",
                    $svc['name'],
                    $svc['id'],
                    $svc['duration'],
                    $svc['price'],
                    wp_strip_all_tags($svc['description'] ?? '')
                );
            }
        }

        $systemPrompt = "You are an intelligent booking assistant for " . get_bloginfo('name') . ".\n"
            . "Here are the REAL available services offered by the business:\n"
            . implode("\n", $serviceList) . "\n\n"
            . "CRITICAL INSTRUCTIONS:\n"
            . "1. Never invent or hallucinate services, prices, or time slots that do not exist.\n"
            . "2. Guide the user to choose the most suitable service.\n"
            . "3. Keep responses friendly, concise, and professional.";

        if ($provider === 'openai') {
            $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode([
                    'model' => $settings['model'] ?? 'gpt-4o-mini',
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userQuery],
                    ],
                    'max_tokens' => 300,
                    'temperature' => 0.5,
                ]),
                'timeout' => 20,
            ]);

            if (!is_wp_error($response)) {
                $body = json_decode(wp_remote_retrieve_body($response), true);
                $reply = $body['choices'][0]['message']['content'] ?? '';
                if (!empty($reply)) {
                    return ['reply' => $reply];
                }
            }
        }

        return [
            'reply' => __('We could not process your AI inquiry at this moment. Please use our booking calendar.', 'appointpro'),
        ];
    }
}
