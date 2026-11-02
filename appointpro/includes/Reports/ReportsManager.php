<?php
declare(strict_types=1);

namespace AppointPro\Reports;

use AppointPro\Helpers\DateTimeHelper;

/**
 * Analytics, Real KPI Aggregation, Financial Reports, and CSV Exporter.
 */
class ReportsManager {
    /**
     * Get real KPI dashboard summary metrics directly from database.
     *
     * @param string|null $startDate
     * @param string|null $endDate
     * @param int|null $locationId
     * @return array<string, mixed>
     */
    public function getDashboardSummary(?string $startDate = null, ?string $endDate = null, ?int $locationId = null): array {
        global $wpdb;

        $apptTable = $wpdb->prefix . 'appointpro_appointments';
        $custTable = $wpdb->prefix . 'appointpro_customers';
        $svcTable = $wpdb->prefix . 'appointpro_services';
        $staffTable = $wpdb->prefix . 'appointpro_staff';

        $nowUtc = gmdate('Y-m-d H:i:s');
        $todayStartUtc = DateTimeHelper::toUtc(date('Y-m-d 00:00:00'));
        $todayEndUtc = DateTimeHelper::toUtc(date('Y-m-d 23:59:59'));

        $totalAppts = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$apptTable}`");
        $todayAppts = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `{$apptTable}` WHERE start_time >= %s AND start_time <= %s",
            $todayStartUtc,
            $todayEndUtc
        ));

        $upcomingAppts = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM `{$apptTable}` WHERE start_time > %s AND status = 'confirmed'",
            $nowUtc
        ));

        $pendingAppts = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$apptTable}` WHERE status = 'pending'");
        $confirmedAppts = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$apptTable}` WHERE status = 'confirmed'");
        $cancelledAppts = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$apptTable}` WHERE status = 'cancelled'");

        $totalCustomers = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$custTable}`");
        $totalServices = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$svcTable}` WHERE status = 'active'");
        $totalStaff = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$staffTable}` WHERE status = 'active'");

        // Financial aggregates (Actual database totals)
        $finRow = $wpdb->get_row(
            "SELECT 
                COALESCE(SUM(final_price), 0) AS total_revenue,
                COALESCE(SUM(paid_amount), 0) AS collected_payments,
                COALESCE(SUM(CASE WHEN status != 'cancelled' THEN (final_price - paid_amount) ELSE 0 END), 0) AS outstanding_balance
             FROM `{$apptTable}` WHERE status != 'cancelled'",
            ARRAY_A
        );

        $totalRevenue = (float) ($finRow['total_revenue'] ?? 0.0);
        $collectedPayments = (float) ($finRow['collected_payments'] ?? 0.0);
        $outstandingBalance = max(0.0, (float) ($finRow['outstanding_balance'] ?? 0.0));

        // Upcoming appointments list
        $upcomingList = $wpdb->get_results($wpdb->prepare(
            "SELECT a.id, a.booking_reference, a.start_time, a.status, a.final_price,
                    s.name AS service_name, s.color AS service_color,
                    st.full_name AS staff_name
             FROM `{$apptTable}` a
             LEFT JOIN `{$svcTable}` s ON a.service_id = s.id
             LEFT JOIN `{$staffTable}` st ON a.staff_id = st.id
             WHERE a.start_time >= %s AND a.status IN ('confirmed','pending')
             ORDER BY a.start_time ASC
             LIMIT 5",
            $nowUtc
        ), ARRAY_A) ?: [];

        foreach ($upcomingList as &$item) {
            $item['formatted_start'] = DateTimeHelper::formatForDisplay($item['start_time']);
        }

        // Recent audit activity
        $auditTable = $wpdb->prefix . 'appointpro_audit_logs';
        $recentActivity = $wpdb->get_results(
            "SELECT * FROM `{$auditTable}` ORDER BY created_at DESC LIMIT 10",
            ARRAY_A
        ) ?: [];

        return [
            'total_appointments' => $totalAppts,
            'today_appointments' => $todayAppts,
            'upcoming_appointments' => $upcomingAppts,
            'pending_appointments' => $pendingAppts,
            'confirmed_appointments' => $confirmedAppts,
            'cancelled_appointments' => $cancelledAppts,
            'total_customers' => $totalCustomers,
            'total_services' => $totalServices,
            'total_staff' => $totalStaff,
            'total_revenue' => $totalRevenue,
            'collected_payments' => $collectedPayments,
            'outstanding_balance' => $outstandingBalance,
            'upcoming_list' => $upcomingList,
            'recent_activity' => $recentActivity,
        ];
    }

    /**
     * Get monthly or daily revenue breakdown for charts.
     *
     * @param int $days
     * @return array<int, array{date: string, revenue: float, bookings: int}>
     */
    public function getRevenueChartData(int $days = 30): array {
        global $wpdb;
        $apptTable = $wpdb->prefix . 'appointpro_appointments';
        $sinceUtc = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(start_time) AS appt_date,
                    COUNT(id) AS booking_count,
                    COALESCE(SUM(paid_amount), 0) AS total_revenue
             FROM `{$apptTable}`
             WHERE start_time >= %s AND status != 'cancelled'
             GROUP BY DATE(start_time)
             ORDER BY appt_date ASC",
            $sinceUtc
        ), ARRAY_A) ?: [];

        $data = [];
        foreach ($rows as $row) {
            $data[] = [
                'date' => $row['appt_date'],
                'bookings' => (int) $row['booking_count'],
                'revenue' => (float) $row['total_revenue'],
            ];
        }

        return $data;
    }

    /**
     * Export appointments to CSV format.
     *
     * @param string|null $startDate
     * @param string|null $endDate
     * @return string CSV text content
     */
    public function exportAppointmentsCsv(?string $startDate = null, ?string $endDate = null): string {
        global $wpdb;
        $apptTable = $wpdb->prefix . 'appointpro_appointments';
        $svcTable = $wpdb->prefix . 'appointpro_services';
        $staffTable = $wpdb->prefix . 'appointpro_staff';
        $custTable = $wpdb->prefix . 'appointpro_customers';
        $acTable = $wpdb->prefix . 'appointpro_appointment_customers';

        $where = "WHERE 1=1";
        $params = [];

        if ($startDate && $endDate) {
            $where .= " AND a.start_time BETWEEN %s AND %s";
            $params = [DateTimeHelper::toUtc($startDate . ' 00:00:00'), DateTimeHelper::toUtc($endDate . ' 23:59:59')];
        }

        $sql = "SELECT a.booking_reference, a.start_time, a.end_time, a.status, a.final_price, a.paid_amount, a.payment_status,
                       s.name AS service_name, st.full_name AS staff_name,
                       c.first_name, c.last_name, c.email, c.phone
                FROM `{$apptTable}` a
                LEFT JOIN `{$svcTable}` s ON a.service_id = s.id
                LEFT JOIN `{$staffTable}` st ON a.staff_id = st.id
                LEFT JOIN `{$acTable}` ac ON a.id = ac.appointment_id
                LEFT JOIN `{$custTable}` c ON ac.customer_id = c.id
                {$where}
                ORDER BY a.start_time DESC";

        $records = !empty($params) ? $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);

        $output = fopen('php://temp', 'r+');
        fputcsv($output, [
            'Reference',
            'Service',
            'Staff',
            'Customer Name',
            'Customer Email',
            'Customer Phone',
            'Date & Time (UTC)',
            'Status',
            'Final Price',
            'Paid Amount',
            'Payment Status',
        ]);

        foreach ($records ?: [] as $rec) {
            fputcsv($output, [
                $rec['booking_reference'],
                $rec['service_name'] ?? '',
                $rec['staff_name'] ?? '',
                trim(($rec['first_name'] ?? '') . ' ' . ($rec['last_name'] ?? '')),
                $rec['email'] ?? '',
                $rec['phone'] ?? '',
                $rec['start_time'],
                $rec['status'],
                $rec['final_price'],
                $rec['paid_amount'],
                $rec['payment_status'],
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv ?: '';
    }
}
