<?php
declare(strict_types=1);

namespace AppointPro\Security;

use WP_Error;

/**
 * Centralized Security, Nonce Validation, and Sanitization Service.
 */
class Security {
    public const NONCE_ACTION_ADMIN = 'appointpro_admin_action';
    public const NONCE_ACTION_FRONTEND = 'appointpro_frontend_action';
    public const NONCE_HEADER = 'X-WP-Nonce';

    /**
     * Verify a WordPress nonce.
     *
     * @param string|null $nonce
     * @param string $action
     * @return bool
     */
    public static function verifyNonce(?string $nonce, string $action = self::NONCE_ACTION_ADMIN): bool {
        if (empty($nonce)) {
            return false;
        }
        return (bool) wp_verify_nonce($nonce, $action);
    }

    /**
     * Create a WordPress nonce.
     *
     * @param string $action
     * @return string
     */
    public static function createNonce(string $action = self::NONCE_ACTION_ADMIN): string {
        return wp_create_nonce($action);
    }

    /**
     * Check user capability or return WP_Error.
     *
     * @param string $capability
     * @return true|WP_Error
     */
    public static function authorize(string $capability): true|WP_Error {
        if (!current_user_can($capability)) {
            return new WP_Error(
                'rest_forbidden',
                __('Sorry, you do not have permission to perform this action.', 'appointpro'),
                ['status' => 403]
            );
        }
        return true;
    }

    /**
     * Sanitize an incoming request array recursively.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public static function sanitizeRecursive(array $data): array {
        $clean = [];
        foreach ($data as $key => $val) {
            $cleanKey = sanitize_key($key);
            if (is_array($val)) {
                $clean[$cleanKey] = self::sanitizeRecursive($val);
            } elseif (is_string($val)) {
                $clean[$cleanKey] = sanitize_text_field($val);
            } else {
                $clean[$cleanKey] = $val;
            }
        }
        return $clean;
    }

    /**
     * Generate a cryptographically secure random token.
     *
     * @param int $length
     * @return string
     */
    public static function generateSecureToken(int $length = 32): string {
        try {
            return bin2hex(random_bytes($length));
        } catch (\Exception $e) {
            return wp_generate_password($length * 2, false);
        }
    }

    /**
     * Rate limiting check via transient storage.
     *
     * @param string $actionKey
     * @param int $maxAttempts
     * @param int $decaySeconds
     * @return bool True if permitted, false if rate limit exceeded.
     */
    public static function checkRateLimit(string $actionKey, int $maxAttempts = 10, int $decaySeconds = 60): bool {
        $ip = sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $transientKey = 'appointpro_rate_' . md5($actionKey . '_' . $ip);
        $attempts = (int) get_transient($transientKey);

        if ($attempts >= $maxAttempts) {
            return false;
        }

        set_transient($transientKey, $attempts + 1, $decaySeconds);
        return true;
    }

    /**
     * Validate an email address.
     *
     * @param string $email
     * @return string|null
     */
    public static function sanitizeEmail(string $email): ?string {
        $clean = sanitize_email($email);
        return is_email($clean) ? $clean : null;
    }

    /**
     * Object level authorization: ensure staff user can only view or mutate their own appointment.
     *
     * @param int $appointmentStaffId
     * @return bool
     */
    public static function canAccessStaffResource(int $appointmentStaffId): bool {
        if (current_user_can('manage_options') || current_user_can('appointpro_manage_appointments')) {
            return true;
        }

        $currentUserId = get_current_user_id();
        if (!$currentUserId) {
            return false;
        }

        global $wpdb;
        $staffTable = $wpdb->prefix . 'appointpro_staff';
        $assignedStaffId = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM `{$staffTable}` WHERE wp_user_id = %d LIMIT 1",
            $currentUserId
        ));

        return $assignedStaffId === $appointmentStaffId;
    }
}
