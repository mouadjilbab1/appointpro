<?php
declare(strict_types=1);

namespace AppointPro\Calendar;

use AppointPro\Helpers\DateTimeHelper;
use AppointPro\Security\Security;

/**
 * Administrative Calendar Manager: Queries, Formats, and Validates Calendar Views.
 */
class CalendarManager {
    /**
     * Get calendar events for a date range with filters.
     *
     * @param string $startDate "Y-m-d"
     * @param string $endDate "Y-m-d"
     * @param int|null $staffId
     * @param int|null $serviceId
     * @param int|null $locationId
     * @return array<int, array<string, mixed>>
     */
    public function getEvents(
        string $startDate,
        string $endDate,
        ?int $staffId = null,
        ?int $serviceId = null,
        ?int $locationId = null
    ): array {
        global $wpdb;
        $apptTable = $wpdb->prefix . 'appointpro_appointments';
        $svcTable = $wpdb->prefix . 'appointpro_services';
        $staffTable = $wpdb->prefix . 'appointpro_staff';
        $custTable = $wpdb->prefix . 'appointpro_customers';
        $acTable = $wpdb->prefix . 'appointpro_appointment_customers';

        $startUtc = DateTimeHelper::toUtc($startDate . ' 00:00:00');
        $endUtc = DateTimeHelper::toUtc($endDate . ' 23:59:59');

        $where = "WHERE a.start_time <= %s AND a.end_time >= %s AND a.status != 'cancelled'";
        $params = [$endUtc, $startUtc];

        // Object-level staff check: if logged-in user is a staff member without full admin access
        if (!current_user_can('appointpro_manage_appointments')) {
            $currentUserId = get_current_user_id();
            $assignedStaffId = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM `{$staffTable}` WHERE wp_user_id = %d LIMIT 1",
                $currentUserId
            ));
            $staffId = $assignedStaffId;
        }

        if ($staffId && $staffId > 0) {
            $where .= " AND a.staff_id = %d";
            $params[] = $staffId;
        }

        if ($serviceId && $serviceId > 0) {
            $where .= " AND a.service_id = %d";
            $params[] = $serviceId;
        }

        if ($locationId && $locationId > 0) {
            $where .= " AND a.location_id = %d";
            $params[] = $locationId;
        }

        $sql = "SELECT a.*, 
                       s.name AS service_name, s.color AS service_color,
                       st.full_name AS staff_name, st.avatar_url AS staff_avatar,
                       c.first_name, c.last_name, c.email, c.phone
                FROM `{$apptTable}` a
                LEFT JOIN `{$svcTable}` s ON a.service_id = s.id
                LEFT JOIN `{$staffTable}` st ON a.staff_id = st.id
                LEFT JOIN `{$acTable}` ac ON a.id = ac.appointment_id
                LEFT JOIN `{$custTable}` c ON ac.customer_id = c.id
                {$where}
                ORDER BY a.start_time ASC";

        $results = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A) ?: [];

        $events = [];
        $localTz = DateTimeHelper::getBusinessTimezone();

        foreach ($results as $row) {
            $events[] = [
                'id' => (int) $row['id'],
                'title' => sprintf('%s - %s %s', $row['service_name'] ?? 'Booking', $row['first_name'] ?? '', $row['last_name'] ?? ''),
                'start' => DateTimeHelper::toLocal($row['start_time'], $localTz),
                'end' => DateTimeHelper::toLocal($row['end_time'], $localTz),
                'utc_start' => $row['start_time'],
                'utc_end' => $row['end_time'],
                'status' => $row['status'],
                'color' => $row['service_color'] ?: '#3b82f6',
                'booking_reference' => $row['booking_reference'],
                'staff_name' => $row['staff_name'],
                'staff_id' => (int) $row['staff_id'],
                'service_id' => (int) $row['service_id'],
                'customer_name' => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
                'customer_email' => $row['email'],
                'customer_phone' => $row['phone'],
                'final_price' => (float) $row['final_price'],
                'paid_amount' => (float) $row['paid_amount'],
                'payment_status' => $row['payment_status'],
            ];
        }

        return $events;
    }

    /**
     * Generate iCalendar (.ics) export data.
     *
     * @param array<int, array<string, mixed>> $events
     * @return string
     */
    public function generateIcs(array $events): string {
        $ics = "BEGIN:VCALENDAR\r\n";
        $ics .= "VERSION:2.0\r\n";
        $ics .= "PRODID:-//AppointPro//EN\r\n";
        $ics .= "CALSCALE:GREGORIAN\r\n";
        $ics .= "METHOD:PUBLISH\r\n";

        foreach ($events as $e) {
            $dtStart = gmdate('Ymd\THis\Z', strtotime($e['utc_start']));
            $dtEnd = gmdate('Ymd\THis\Z', strtotime($e['utc_end']));
            $uid = 'appointpro-' . $e['booking_reference'] . '@' . parse_url(site_url(), PHP_URL_HOST);

            $ics .= "BEGIN:VEVENT\r\n";
            $ics .= "UID:{$uid}\r\n";
            $ics .= "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n";
            $ics .= "DTSTART:{$dtStart}\r\n";
            $ics .= "DTEND:{$dtEnd}\r\n";
            $ics .= "SUMMARY:" . addcslashes($e['title'], ",;") . "\r\n";
            $ics .= "DESCRIPTION:" . addcslashes("Reference: {$e['booking_reference']}\nCustomer: {$e['customer_name']}\nStaff: {$e['staff_name']}", ",;") . "\r\n";
            $ics .= "STATUS:CONFIRMED\r\n";
            $ics .= "END:VEVENT\r\n";
        }

        $ics .= "END:VCALENDAR\r\n";
        return $ics;
    }
}
