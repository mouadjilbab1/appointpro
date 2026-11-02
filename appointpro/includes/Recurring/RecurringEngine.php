<?php
declare(strict_types=1);

namespace AppointPro\Recurring;

use AppointPro\Availability\AvailabilityEngine;
use AppointPro\Booking\BookingEngine;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;

/**
 * Recurring Appointments Engine: Computes dates, validates each occurrence, and manages recurring series.
 */
class RecurringEngine {
    protected AvailabilityEngine $availabilityEngine;
    protected BookingEngine $bookingEngine;

    public function __construct(
        ?AvailabilityEngine $availabilityEngine = null,
        ?BookingEngine $bookingEngine = null
    ) {
        $this->availabilityEngine = $availabilityEngine ?: new AvailabilityEngine();
        $this->bookingEngine = $bookingEngine ?: new BookingEngine();
    }

    /**
     * Compute scheduled timestamps for a recurring series.
     *
     * @param string $initialUtcStart "Y-m-d H:i:s"
     * @param int $durationMinutes
     * @param string $frequency 'daily', 'weekly', 'monthly'
     * @param int $interval e.g. 1 (every week) or 2 (every 2 weeks)
     * @param int $occurrences Total appointments
     * @return array<int, array{start: string, end: string}>
     */
    public function generateOccurrenceDates(
        string $initialUtcStart,
        int $durationMinutes,
        string $frequency,
        int $interval = 1,
        int $occurrences = 5
    ): array {
        $dates = [];
        $utc = new DateTimeZone('UTC');
        $start = new DateTimeImmutable($initialUtcStart, $utc);

        for ($i = 0; $i < $occurrences; $i++) {
            if ($i === 0) {
                $occurrenceStart = $start;
            } else {
                $modifier = match ($frequency) {
                    'daily' => sprintf('+%d days', $i * $interval),
                    'weekly' => sprintf('+%d weeks', $i * $interval),
                    'monthly' => sprintf('+%d months', $i * $interval),
                    default => sprintf('+%d weeks', $i * $interval),
                };
                $occurrenceStart = $start->modify($modifier);
            }

            $occurrenceEnd = $occurrenceStart->modify("+{$durationMinutes} minutes");

            $dates[] = [
                'start' => $occurrenceStart->format('Y-m-d H:i:s'),
                'end' => $occurrenceEnd->format('Y-m-d H:i:s'),
            ];
        }

        return $dates;
    }

    /**
     * Create a recurring appointment series with availability validation for every occurrence.
     *
     * @param array<string, mixed> $payload Base booking payload
     * @param string $frequency
     * @param int $interval
     * @param int $occurrences
     * @return array{series_id: int, appointments: array<int>}
     */
    public function createRecurringSeries(
        array $payload,
        string $frequency,
        int $interval,
        int $occurrences
    ): array {
        global $wpdb;

        $serviceId = (int) ($payload['service_id'] ?? 0);
        $durationMinutes = 60; // duration from service
        $initialStart = sanitize_text_field($payload['start_time'] ?? '');

        $dates = $this->generateOccurrenceDates($initialStart, $durationMinutes, $frequency, $interval, $occurrences);

        // Pre-validate all occurrences
        $apptTable = $wpdb->prefix . 'appointpro_appointments';
        $staffId = (int) ($payload['staff_id'] ?? 0);

        foreach ($dates as $idx => $slot) {
            $conflict = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM `{$apptTable}`
                 WHERE staff_id = %d AND status IN ('confirmed','pending')
                 AND (start_time < %s AND end_time > %s)",
                $staffId,
                $slot['end'],
                $slot['start']
            ));

            if ($conflict > 0) {
                throw new RuntimeException(sprintf(
                    __('Occurrence %d on %s is conflicting with another booking.', 'appointpro'),
                    $idx + 1,
                    $slot['start']
                ));
            }
        }

        // Create Series Record
        $seriesTable = $wpdb->prefix . 'appointpro_recurring_series';
        $customerId = (int) ($payload['customer_id'] ?? 0);
        $now = gmdate('Y-m-d H:i:s');

        $wpdb->insert($seriesTable, [
            'customer_id' => $customerId,
            'frequency' => $frequency,
            'repeat_interval' => $interval,
            'total_occurrences' => $occurrences,
            'completed_occurrences' => 0,
            'status' => 'active',
            'created_at' => $now,
        ]);

        $seriesId = (int) $wpdb->insert_id;
        $createdAppointments = [];

        foreach ($dates as $slot) {
            $occurrencePayload = $payload;
            $occurrencePayload['start_time'] = $slot['start'];
            $occurrencePayload['end_time'] = $slot['end'];
            $result = $this->bookingEngine->createAppointment($occurrencePayload);

            $apptId = $result['appointment_id'];
            $wpdb->update($apptTable, ['series_id' => $seriesId], ['id' => $apptId]);
            $createdAppointments[] = $apptId;
        }

        return [
            'series_id' => $seriesId,
            'appointments' => $createdAppointments,
        ];
    }
}
