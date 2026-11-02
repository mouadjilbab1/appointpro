<?php
declare(strict_types=1);

namespace AppointPro\Staff;

/**
 * Staff, Schedules, Breaks, and Special Days Repository.
 */
class StaffRepository {
    /**
     * Get all staff members with their assigned service IDs.
     *
     * @param bool $activeOnly
     * @return array<int, array<string, mixed>>
     */
    public function getAll(bool $activeOnly = true): array {
        global $wpdb;
        $table = $wpdb->prefix . 'appointpro_staff';
        $where = $activeOnly ? "WHERE status = 'active'" : "";
        $staffMembers = $wpdb->get_results("SELECT * FROM `{$table}` {$where} ORDER BY full_name ASC", ARRAY_A);

        foreach ($staffMembers as &$staff) {
            $staff['service_ids'] = $this->getStaffServiceIds((int) $staff['id']);
        }

        return $staffMembers;
    }

    /**
     * Find staff by ID.
     *
     * @param int $id
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'appointpro_staff';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$table}` WHERE id = %d LIMIT 1", $id), ARRAY_A);

        if (!$row) {
            return null;
        }

        $row['service_ids'] = $this->getStaffServiceIds($id);
        $row['schedule'] = $this->getWeeklySchedule($id);
        $row['special_days'] = $this->getSpecialDays($id);
        return $row;
    }

    /**
     * Save staff profile.
     *
     * @param array<string, mixed> $data
     * @param int|null $id
     * @return int Staff ID
     */
    public function save(array $data, ?int $id = null): int {
        global $wpdb;
        $table = $wpdb->prefix . 'appointpro_staff';
        $now = gmdate('Y-m-d H:i:s');

        $record = [
            'wp_user_id' => !empty($data['wp_user_id']) ? (int) $data['wp_user_id'] : null,
            'full_name' => sanitize_text_field($data['full_name'] ?? ''),
            'email' => sanitize_email($data['email'] ?? ''),
            'phone' => sanitize_text_field($data['phone'] ?? ''),
            'avatar_url' => esc_url_raw($data['avatar_url'] ?? ''),
            'bio' => sanitize_textarea_field($data['bio'] ?? ''),
            'auto_assign' => isset($data['auto_assign']) ? (int) $data['auto_assign'] : 1,
            'status' => in_array($data['status'] ?? '', ['active', 'inactive'], true) ? $data['status'] : 'active',
            'updated_at' => $now,
        ];

        if ($id && $id > 0) {
            $wpdb->update($table, $record, ['id' => $id]);
            $staffId = $id;
        } else {
            $record['created_at'] = $now;
            $wpdb->insert($table, $record);
            $staffId = (int) $wpdb->insert_id;
            $this->createDefaultWeeklySchedule($staffId);
        }

        // Sync service assignments if provided
        if (isset($data['service_ids']) && is_array($data['service_ids'])) {
            $this->syncServiceAssignments($staffId, array_map('intval', $data['service_ids']));
        }

        return $staffId;
    }

    /**
     * Delete staff member.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'appointpro_staff';
        // Set inactive instead of destroying historical records
        return (bool) $wpdb->update($table, ['status' => 'inactive'], ['id' => $id]);
    }

    /**
     * Get assigned service IDs for a staff member.
     *
     * @param int $staffId
     * @return array<int>
     */
    public function getStaffServiceIds(int $staffId): array {
        global $wpdb;
        $table = $wpdb->prefix . 'appointpro_staff_services';
        $col = $wpdb->get_col($wpdb->prepare("SELECT service_id FROM `{$table}` WHERE staff_id = %d", $staffId));
        return array_map('intval', $col ?: []);
    }

    /**
     * Sync service assignments for a staff member.
     *
     * @param int $staffId
     * @param array<int> $serviceIds
     * @return void
     */
    public function syncServiceAssignments(int $staffId, array $serviceIds): void {
        global $wpdb;
        $table = $wpdb->prefix . 'appointpro_staff_services';

        $wpdb->delete($table, ['staff_id' => $staffId]);

        foreach ($serviceIds as $serviceId) {
            if ($serviceId > 0) {
                $wpdb->insert($table, [
                    'staff_id' => $staffId,
                    'service_id' => $serviceId,
                ]);
            }
        }
    }

    /**
     * Get weekly schedule with breaks for a staff member.
     *
     * @param int $staffId
     * @return array<int, array<string, mixed>>
     */
    public function getWeeklySchedule(int $staffId): array {
        global $wpdb;
        $schedTable = $wpdb->prefix . 'appointpro_schedules';
        $breakTable = $wpdb->prefix . 'appointpro_schedule_breaks';

        $schedules = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM `{$schedTable}` WHERE staff_id = %d ORDER BY day_of_week ASC", $staffId),
            ARRAY_A
        );

        foreach ($schedules as &$sched) {
            $schedId = (int) $sched['id'];
            $sched['breaks'] = $wpdb->get_results(
                $wpdb->prepare("SELECT * FROM `{$breakTable}` WHERE schedule_id = %d ORDER BY start_time ASC", $schedId),
                ARRAY_A
            ) ?: [];
        }

        return $schedules;
    }

    /**
     * Save weekly schedule and breaks for a staff member.
     *
     * @param int $staffId
     * @param array<int, array<string, mixed>> $days
     * @return void
     */
    public function saveWeeklySchedule(int $staffId, array $days): void {
        global $wpdb;
        $schedTable = $wpdb->prefix . 'appointpro_schedules';
        $breakTable = $wpdb->prefix . 'appointpro_schedule_breaks';

        foreach ($days as $day) {
            $dayOfWeek = (int) ($day['day_of_week'] ?? 0);
            $startTime = sanitize_text_field($day['start_time'] ?? '09:00:00');
            $endTime = sanitize_text_field($day['end_time'] ?? '17:00:00');
            $isWorking = !empty($day['is_working']) ? 1 : 0;

            $existingId = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM `{$schedTable}` WHERE staff_id = %d AND day_of_week = %d LIMIT 1",
                $staffId,
                $dayOfWeek
            ));

            if ($existingId) {
                $scheduleId = (int) $existingId;
                $wpdb->update($schedTable, [
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'is_working' => $isWorking,
                ], ['id' => $scheduleId]);
            } else {
                $wpdb->insert($schedTable, [
                    'staff_id' => $staffId,
                    'location_id' => null,
                    'day_of_week' => $dayOfWeek,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'is_working' => $isWorking,
                ]);
                $scheduleId = (int) $wpdb->insert_id;
            }

            // Sync breaks for this day
            $wpdb->delete($breakTable, ['schedule_id' => $scheduleId]);
            if (!empty($day['breaks']) && is_array($day['breaks'])) {
                foreach ($day['breaks'] as $brk) {
                    $bStart = sanitize_text_field($brk['start_time'] ?? '');
                    $bEnd = sanitize_text_field($brk['end_time'] ?? '');
                    if ($bStart && $bEnd) {
                        $wpdb->insert($breakTable, [
                            'schedule_id' => $scheduleId,
                            'start_time' => $bStart,
                            'end_time' => $bEnd,
                            'label' => sanitize_text_field($brk['label'] ?? 'Break'),
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Create default standard Monday-Friday 09:00-17:00 weekly schedule for a new staff member.
     *
     * @param int $staffId
     * @return void
     */
    protected function createDefaultWeeklySchedule(int $staffId): void {
        global $wpdb;
        $schedTable = $wpdb->prefix . 'appointpro_schedules';
        $breakTable = $wpdb->prefix . 'appointpro_schedule_breaks';

        for ($day = 0; $day <= 6; $day++) {
            // Sunday (0) and Saturday (6) off by default; Monday (1) to Friday (5) working
            $isWorking = ($day >= 1 && $day <= 5) ? 1 : 0;
            $wpdb->insert($schedTable, [
                'staff_id' => $staffId,
                'location_id' => null,
                'day_of_week' => $day,
                'start_time' => '09:00:00',
                'end_time' => '17:00:00',
                'is_working' => $isWorking,
            ]);
            $scheduleId = (int) $wpdb->insert_id;

            if ($isWorking) {
                // Default 1 hour lunch break 12:00-13:00
                $wpdb->insert($breakTable, [
                    'schedule_id' => $scheduleId,
                    'start_time' => '12:00:00',
                    'end_time' => '13:00:00',
                    'label' => 'Lunch',
                ]);
            }
        }
    }

    /**
     * Get special days and holidays for staff.
     *
     * @param int $staffId
     * @return array<int, array<string, mixed>>
     */
    public function getSpecialDays(int $staffId): array {
        global $wpdb;
        $table = $wpdb->prefix . 'appointpro_special_days';
        return $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM `{$table}` WHERE staff_id = %d OR staff_id IS NULL ORDER BY start_date ASC", $staffId),
            ARRAY_A
        ) ?: [];
    }

    /**
     * Save special day / holiday.
     *
     * @param array<string, mixed> $data
     * @return int
     */
    public function saveSpecialDay(array $data): int {
        global $wpdb;
        $table = $wpdb->prefix . 'appointpro_special_days';

        $record = [
            'staff_id' => !empty($data['staff_id']) ? (int) $data['staff_id'] : null,
            'start_date' => sanitize_text_field($data['start_date'] ?? ''),
            'end_date' => sanitize_text_field($data['end_date'] ?? $data['start_date'] ?? ''),
            'start_time' => !empty($data['start_time']) ? sanitize_text_field($data['start_time']) : null,
            'end_time' => !empty($data['end_time']) ? sanitize_text_field($data['end_time']) : null,
            'is_day_off' => isset($data['is_day_off']) ? (int) $data['is_day_off'] : 1,
            'reason' => sanitize_text_field($data['reason'] ?? ''),
        ];

        if (!empty($data['id'])) {
            $wpdb->update($table, $record, ['id' => (int) $data['id']]);
            return (int) $data['id'];
        }

        $wpdb->insert($table, $record);
        return (int) $wpdb->insert_id;
    }

    /**
     * Automatic staff assignment: pick eligible staff for a service.
     * Selects least-booked staff member on the requested date to balance workload.
     *
     * @param int $serviceId
     * @param string $date "Y-m-d"
     * @param array<int> $excludeStaffIds
     * @return int|null Staff ID or null if none available
     */
    public function autoAssignStaff(int $serviceId, string $date, array $excludeStaffIds = []): ?int {
        global $wpdb;
        $staffTable = $wpdb->prefix . 'appointpro_staff';
        $staffSvcTable = $wpdb->prefix . 'appointpro_staff_services';
        $apptTable = $wpdb->prefix . 'appointpro_appointments';

        $query = "SELECT s.id, COUNT(a.id) AS booking_count
                  FROM `{$staffTable}` s
                  INNER JOIN `{$staffSvcTable}` ss ON s.id = ss.staff_id
                  LEFT JOIN `{$apptTable}` a ON (s.id = a.staff_id AND DATE(a.start_time) = %s AND a.status IN ('confirmed','pending'))
                  WHERE ss.service_id = %d AND s.status = 'active' AND s.auto_assign = 1";

        if (!empty($excludeStaffIds)) {
            $inClause = implode(',', array_map('intval', $excludeStaffIds));
            $query .= " AND s.id NOT IN ({$inClause})";
        }

        $query .= " GROUP BY s.id ORDER BY booking_count ASC, RAND() LIMIT 1";

        $bestStaffId = $wpdb->get_var($wpdb->prepare($query, $date, $serviceId));
        return $bestStaffId ? (int) $bestStaffId : null;
    }
}
