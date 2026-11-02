<?php
declare(strict_types=1);

namespace AppointPro\Helpers;

/**
 * String and data formatting utilities.
 */
class Formatting {
    /**
     * Generate a unique, professional booking reference number.
     *
     * @return string e.g. "AP-892415"
     */
    public static function generateBookingReference(): string {
        $prefix = 'AP';
        $random = mt_rand(100000, 999999);
        return sprintf('%s-%d', $prefix, $random);
    }

    /**
     * Format duration in minutes into a human-readable string.
     *
     * @param int $minutes
     * @return string e.g. "1 hr 30 mins" or "45 mins"
     */
    public static function formatDuration(int $minutes): string {
        if ($minutes < 60) {
            return sprintf(_n('%d min', '%d mins', $minutes, 'appointpro'), $minutes);
        }

        $hours = intdiv($minutes, 60);
        $remMinutes = $minutes % 60;

        if ($remMinutes === 0) {
            return sprintf(_n('%d hr', '%d hrs', $hours, 'appointpro'), $hours);
        }

        return sprintf(
            __('%d hr %d min', 'appointpro'),
            $hours,
            $remMinutes
        );
    }

    /**
     * Sanitize and standardize telephone numbers.
     *
     * @param string $phone
     * @return string
     */
    public static function sanitizePhone(string $phone): string {
        return preg_replace('/[^\d+]/', '', trim($phone)) ?: '';
    }
}
