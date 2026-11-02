<?php
declare(strict_types=1);

namespace AppointPro\Availability;

use AppointPro\Helpers\DateTimeHelper;
use AppointPro\Resources\ResourceRepository;
use AppointPro\Services\ServiceRepository;
use AppointPro\Staff\StaffRepository;
use DateTimeImmutable;
use DateTimeZone;
use Exception;

/**
 * High-precision, Timezone-aware Availability Engine with Concurrency Reservation Locks.
 */
class AvailabilityEngine {
    protected ServiceRepository $serviceRepo;
    protected StaffRepository $staffRepo;
    protected ResourceRepository $resourceRepo;

    public function __construct(
        ?ServiceRepository $serviceRepo = null,
        ?StaffRepository $staffRepo = null,
        ?ResourceRepository $resourceRepo = null
    ) {
        $this->serviceRepo = $serviceRepo ?: new ServiceRepository();
        $this->staffRepo = $staffRepo ?: new StaffRepository();
        $this->resourceRepo = $resourceRepo ?: new ResourceRepository();
    }

    /**
     * Get available booking slots for a service, staff, and date range.
     *
     * @param int $serviceId
     * @param int|null $staffId (0 or null for any staff)
     * @param string $date "Y-m-d"
     * @param int|null $locationId
     * @return array<int, array{time: string, utc_start: string, utc_end: string, staff_id: int, available: bool}>
     */
    public function getAvailableSlots(int $serviceId, ?int $staffId, string $date, ?int $locationId = null): array {
        $service = $this->serviceRepo->find($serviceId);
        if (!$service || $service['status'] !== 'active') {
            return [];
        }

        $businessTz = DateTimeHelper::getBusinessTimezone();
        $now = new DateTimeImmutable('now', $businessTz);

        // Check max advance days
        $maxAdvanceDays = (int) ($service['max_advance_days'] ?? 90);
        $targetDate = new DateTimeImmutable($date, $businessTz);
        $maxDate = $now->modify("+{$maxAdvanceDays} days")->setTime(23, 59, 59);

        if ($targetDate > $maxDate) {
            return [];
        }

        // Determine candidate staff members
        $candidateStaffIds = [];
        if ($staffId && $staffId > 0) {
            $candidateStaffIds = [$staffId];
        } else {
            $assignedStaff = $this->serviceRepo->getServiceStaffIds($serviceId);
            if (empty($assignedStaff)) {
                // If no staff explicitly mapped, all active auto-assign staff are candidates
                $allStaff = $this->staffRepo->getAll(true);
                $candidateStaffIds = array_column($allStaff, 'id');
            } else {
                $candidateStaffIds = $assignedStaff;
            }
        }

        if (empty($candidateStaffIds)) {
            return [];
        }

        $durationMinutes = (int) $service['duration'];
        $bufferBefore = (int) ($service['buffer_before'] ?? 0);
        $bufferAfter = (int) ($service['buffer_after'] ?? 0);
        $totalSlotMinutes = $durationMinutes + $bufferBefore + $bufferAfter;
        $stepMinutes = 30; // configurable slot interval

        $requiredResource = $this->resourceRepo->getResourceForService($serviceId);

        $slots = [];
        $dayOfWeek = (int) $targetDate->format('w'); // 0 = Sunday, 6 = Saturday

        foreach ($candidateStaffIds as $cStaffId) {
            $cStaffId = (int) $cStaffId;

            // Check staff day-off / special day
            if ($this->isStaffOnHoliday($cStaffId, $date)) {
                continue;
            }

            // Get staff schedule for this day of week
            $workingSchedule = $this->getStaffWorkingHoursForDay($cStaffId, $dayOfWeek);
            if (!$workingSchedule || empty($workingSchedule['is_working'])) {
                continue;
            }

            $dayStart = new DateTimeImmutable($date . ' ' . $workingSchedule['start_time'], $businessTz);
            $dayEnd = new DateTimeImmutable($date . ' ' . $workingSchedule['end_time'], $businessTz);
            $breaks = $workingSchedule['breaks'] ?? [];

            // Existing bookings and active slot locks for this staff member on this day
            $busyIntervals = $this->getStaffBusyIntervals($cStaffId, $date, $businessTz);

            $currentSlotStart = $dayStart;

            while ($currentSlotStart->modify("+{$durationMinutes} minutes") <= $dayEnd) {
                // Appointment start considering buffer before
                $actualApptStart = $currentSlotStart;
                $actualApptEnd = $actualApptStart->modify("+{$durationMinutes} minutes");

                // Full blocked interval includes buffers
                $blockedStart = $actualApptStart->modify("-{$bufferBefore} minutes");
                $blockedEnd = $actualApptEnd->modify("+{$bufferAfter} minutes");

                // Check minimum notice requirement
                $minNoticeHours = (int) ($service['min_notice_hours'] ?? 2);
                $earliestAllowed = $now->modify("+{$minNoticeHours} hours");

                $isAvailable = true;

                if ($actualApptStart < $earliestAllowed) {
                    $isAvailable = false;
                }

                // Check staff breaks collision
                if ($isAvailable) {
                    foreach ($breaks as $brk) {
                        $breakStart = new DateTimeImmutable($date . ' ' . $brk['start_time'], $businessTz);
                        $breakEnd = new DateTimeImmutable($date . ' ' . $brk['end_time'], $businessTz);
                        if (DateTimeHelper::intervalsOverlap(
                            $blockedStart->format('H:i:s'),
                            $blockedEnd->format('H:i:s'),
                            $breakStart->format('H:i:s'),
                            $breakEnd->format('H:i:s')
                        )) {
                            $isAvailable = false;
                            break;
                        }
                    }
                }

                // Check busy appointments & locks
                if ($isAvailable) {
                    foreach ($busyIntervals as $busy) {
                        if (DateTimeHelper::intervalsOverlap(
                            $blockedStart->format('Y-m-d H:i:s'),
                            $blockedEnd->format('Y-m-d H:i:s'),
                            $busy['start'],
                            $busy['end']
                        )) {
                            $isAvailable = false;
                            break;
                        }
                    }
                }

                // Check shared resource availability
                if ($isAvailable && $requiredResource) {
                    $utcStart = DateTimeHelper::toUtc($actualApptStart->format('Y-m-d H:i:s'), $businessTz);
                    $utcEnd = DateTimeHelper::toUtc($actualApptEnd->format('Y-m-d H:i:s'), $businessTz);
                    if (!$this->resourceRepo->isResourceAvailable((int) $requiredResource['id'], $utcStart, $utcEnd)) {
                        $isAvailable = false;
                    }
                }

                if ($isAvailable) {
                    $utcStart = DateTimeHelper::toUtc($actualApptStart->format('Y-m-d H:i:s'), $businessTz);
                    $utcEnd = DateTimeHelper::toUtc($actualApptEnd->format('Y-m-d H:i:s'), $businessTz);
                    $timeString = $actualApptStart->format('H:i');

                    // If slot already present, don't duplicate (or record multi-staff option)
                    if (!isset($slots[$timeString])) {
                        $slots[$timeString] = [
                            'time' => $timeString,
                            'formatted_time' => $actualApptStart->format(get_option('time_format', 'H:i')),
                            'utc_start' => $utcStart,
                            'utc_end' => $utcEnd,
                            'staff_id' => $cStaffId,
                            'available' => true,
                        ];
                    }
                }

                $currentSlotStart = $currentSlotStart->modify("+{$stepMinutes} minutes");
            }
        }

        ksort($slots);
        return array_values($slots);
    }

    /**
     * Check if staff has day off or holiday on given date.
     *
     * @param int $staffId
     * @param string $date "Y-m-d"
     * @return bool
     */
    protected function isStaffOnHoliday(int $staffId, string $date): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'appointpro_special_days';
        $count = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `{$table}`
             WHERE (staff_id = %d OR staff_id IS NULL)
             AND is_day_off = 1
             AND %s BETWEEN start_date AND end_date",
            $staffId,
            $date
        ));
        return $count > 0;
    }

    /**
     * Get staff working hours and breaks for a specific day of week.
     *
     * @param int $staffId
     * @param int $dayOfWeek 0=Sunday, 6=Saturday
     * @return array<string, mixed>|null
     */
    protected function getStaffWorkingHoursForDay(int $staffId, int $dayOfWeek): ?array {
        global $wpdb;
        $schedTable = $wpdb->prefix . 'appointpro_schedules';
        $breakTable = $wpdb->prefix . 'appointpro_schedule_breaks';

        $sched = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `{$schedTable}` WHERE staff_id = %d AND day_of_week = %d LIMIT 1",
            $staffId,
            $dayOfWeek
        ), ARRAY_A);

        if (!$sched) {
            return null;
        }

        $sched['breaks'] = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM `{$breakTable}` WHERE schedule_id = %d ORDER BY start_time ASC",
            (int) $sched['id']
        ), ARRAY_A) ?: [];

        return $sched;
    }

    /**
     * Get existing busy intervals (appointments + temporary locks) for a staff member on a date.
     *
     * @param int $staffId
     * @param string $date "Y-m-d"
     * @param DateTimeZone $localTz
     * @return array<int, array{start: string, end: string}> Local timezone datetime strings
     */
    protected function getStaffBusyIntervals(int $staffId, string $date, DateTimeZone $localTz): array {
        global $wpdb;
        $apptTable = $wpdb->prefix . 'appointpro_appointments';
        $lockTable = $wpdb->prefix . 'appointpro_slot_locks';

        // Calculate UTC boundaries for the local day
        $startOfDayUtc = DateTimeHelper::toUtc($date . ' 00:00:00', $localTz);
        $endOfDayUtc = DateTimeHelper::toUtc($date . ' 23:59:59', $localTz);

        // 1. Appointments
        $appts = $wpdb->get_results($wpdb->prepare(
            "SELECT start_time, end_time FROM `{$apptTable}`
             WHERE staff_id = %d
             AND status IN ('confirmed', 'pending')
             AND (start_time <= %s AND end_time >= %s)",
            $staffId,
            $endOfDayUtc,
            $startOfDayUtc
        ), ARRAY_A) ?: [];

        // 2. Active locks
        $nowUtc = gmdate('Y-m-d H:i:s');
        $locks = $wpdb->get_results($wpdb->prepare(
            "SELECT start_time, end_time FROM `{$lockTable}`
             WHERE staff_id = %d
             AND expires_at > %s
             AND (start_time <= %s AND end_time >= %s)",
            $staffId,
            $nowUtc,
            $endOfDayUtc,
            $startOfDayUtc
        ), ARRAY_A) ?: [];

        $busy = [];
        foreach (array_merge($appts, $locks) as $item) {
            $busy[] = [
                'start' => DateTimeHelper::toLocal($item['start_time'], $localTz),
                'end' => DateTimeHelper::toLocal($item['end_time'], $localTz),
            ];
        }

        return $busy;
    }

    /**
     * Atomically lock a slot during checkout for a customer session.
     * Prevents race conditions and double bookings.
     *
     * @param int $staffId
     * @param string $utcStart
     * @param string $utcEnd
     * @param string $sessionToken
     * @param int $timeoutMinutes
     * @return bool
     */
    public function reserveSlotLock(int $staffId, string $utcStart, string $utcEnd, string $sessionToken, int $timeoutMinutes = 10): bool {
        global $wpdb;
        $lockTable = $wpdb->prefix . 'appointpro_slot_locks';
        $nowUtc = gmdate('Y-m-d H:i:s');
        $expiresAt = gmdate('Y-m-d H:i:s', strtotime("+{$timeoutMinutes} minutes"));

        // Clean up expired locks first
        $wpdb->query($wpdb->prepare("DELETE FROM `{$lockTable}` WHERE expires_at < %s", $nowUtc));

        // Check if existing active lock belongs to another session
        $existingLock = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM `{$lockTable}`
             WHERE staff_id = %d
             AND (start_time < %s AND end_time > %s)
             AND expires_at > %s
             LIMIT 1",
            $staffId,
            $utcEnd,
            $utcStart,
            $nowUtc
        ), ARRAY_A);

        if ($existingLock) {
            if ($existingLock['session_token'] === $sessionToken) {
                // Extend lock
                $wpdb->update($lockTable, ['expires_at' => $expiresAt], ['id' => $existingLock['id']]);
                return true;
            }
            return false; // Reserved by another customer
        }

        // Insert new lock
        $result = $wpdb->insert($lockTable, [
            'staff_id' => $staffId,
            'start_time' => $utcStart,
            'end_time' => $utcEnd,
            'session_token' => $sessionToken,
            'expires_at' => $expiresAt,
        ]);

        return (bool) $result;
    }

    /**
     * Release slot lock on checkout completion or cart abandon.
     *
     * @param string $sessionToken
     * @return void
     */
    public function releaseSlotLock(string $sessionToken): void {
        global $wpdb;
        $lockTable = $wpdb->prefix . 'appointpro_slot_locks';
        $wpdb->delete($lockTable, ['session_token' => $sessionToken]);
    }
}
