<?php
declare(strict_types=1);

namespace AppointPro\Helpers;

use DateTimeImmutable;
use DateTimeZone;
use Exception;

/**
 * Timezone-aware date and time manipulation helper.
 */
class DateTimeHelper {
    /**
     * Get the configured business timezone.
     *
     * @return DateTimeZone
     */
    public static function getBusinessTimezone(): DateTimeZone {
        $settings = (array) get_option('appointpro_settings', []);
        $tzString = $settings['timezone'] ?? wp_timezone_string();
        try {
            return new DateTimeZone($tzString ?: 'UTC');
        } catch (Exception $e) {
            return new DateTimeZone('UTC');
        }
    }

    /**
     * Get UTC timezone instance.
     *
     * @return DateTimeZone
     */
    public static function getUtcTimezone(): DateTimeZone {
        return new DateTimeZone('UTC');
    }

    /**
     * Convert a local datetime string in business timezone to UTC datetime string.
     *
     * @param string $localDateTime e.g. "2026-11-15 09:30:00"
     * @param DateTimeZone|null $fromTimezone
     * @return string "2026-11-15 14:30:00"
     */
    public static function toUtc(string $localDateTime, ?DateTimeZone $fromTimezone = null): string {
        $tz = $fromTimezone ?: self::getBusinessTimezone();
        $dt = new DateTimeImmutable($localDateTime, $tz);
        $utcDt = $dt->setTimezone(self::getUtcTimezone());
        return $utcDt->format('Y-m-d H:i:s');
    }

    /**
     * Convert a UTC datetime string to local business timezone datetime string.
     *
     * @param string $utcDateTime
     * @param DateTimeZone|null $toTimezone
     * @return string
     */
    public static function toLocal(string $utcDateTime, ?DateTimeZone $toTimezone = null): string {
        $tz = $toTimezone ?: self::getBusinessTimezone();
        $dt = new DateTimeImmutable($utcDateTime, self::getUtcTimezone());
        $localDt = $dt->setTimezone($tz);
        return $localDt->format('Y-m-d H:i:s');
    }

    /**
     * Check if two time intervals overlap.
     *
     * Standard non-inclusive overlap condition: (StartA < EndB) and (EndA > StartB).
     *
     * @param string $startA
     * @param string $endA
     * @param string $startB
     * @param string $endB
     * @return bool
     */
    public static function intervalsOverlap(string $startA, string $endA, string $startB, string $endB): bool {
        return ($startA < $endB) && ($endA > $startB);
    }

    /**
     * Format a UTC datetime for display in business timezone.
     *
     * @param string $utcDateTime
     * @param string|null $format
     * @return string
     */
    public static function formatForDisplay(string $utcDateTime, ?string $format = null): string {
        $settings = (array) get_option('appointpro_settings', []);
        $dateFormat = $settings['date_format'] ?? get_option('date_format', 'Y-m-d');
        $timeFormat = $settings['time_format'] ?? get_option('time_format', 'H:i');
        $fullFormat = $format ?: ($dateFormat . ' ' . $timeFormat);

        $dt = new DateTimeImmutable($utcDateTime, self::getUtcTimezone());
        $localDt = $dt->setTimezone(self::getBusinessTimezone());
        return $localDt->format($fullFormat);
    }
}
