<?php
declare(strict_types=1);

namespace AppointPro\Customers;

/**
 * Customer CRM, Profiles, and Booking History Repository.
 */
class CustomerRepository {
    /**
     * Get paginated customer list with statistics.
     *
     * @param int $page
     * @param int $perPage
     * @param string $search
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    public function getList(int $page = 1, int $perPage = 20, string $search = ''): array {
        global $wpdb;
        $table = $wpdb->prefix . 'appointpro_customers';
        $offset = max(0, ($page - 1) * $perPage);

        $where = "WHERE 1=1";
        $params = [];

        if (!empty($search)) {
            $like = '%' . $wpdb->esc_like(trim($search)) . '%';
            $where .= " AND (first_name LIKE %s OR last_name LIKE %s OR email LIKE %s OR phone LIKE %s)";
            $params = [$like, $like, $like, $like];
        }

        $countSql = "SELECT COUNT(*) FROM `{$table}` {$where}";
        $total = !empty($params)
            ? (int) $wpdb->get_var($wpdb->prepare($countSql, ...$params))
            : (int) $wpdb->get_var($countSql);

        $dataSql = "SELECT * FROM `{$table}` {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $queryParams = array_merge($params, [$perPage, $offset]);
        $items = $wpdb->get_results($wpdb->prepare($dataSql, ...$queryParams), ARRAY_A) ?: [];

        foreach ($items as &$item) {
            $stats = $this->getCustomerStats((int) $item['id']);
            $item['total_bookings'] = $stats['total_bookings'];
            $item['total_spent'] = $stats['total_spent'];
        }

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    /**
     * Find customer by ID.
     *
     * @param int $id
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'appointpro_customers';
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$table}` WHERE id = %d LIMIT 1", $id), ARRAY_A);
        if ($row) {
            $row['stats'] = $this->getCustomerStats($id);
        }
        return $row;
    }

    /**
     * Find customer by email or create if not exists.
     *
     * @param array{first_name: string, last_name: string, email: string, phone?: string} $data
     * @param int|null $wpUserId
     * @return int Customer ID
     */
    public function findOrCreate(array $data, ?int $wpUserId = null): int {
        global $wpdb;
        $table = $wpdb->prefix . 'appointpro_customers';
        $email = sanitize_email($data['email']);

        $existing = $wpdb->get_row($wpdb->prepare("SELECT id, wp_user_id FROM `{$table}` WHERE email = %s LIMIT 1", $email), ARRAY_A);

        if ($existing) {
            $customerId = (int) $existing['id'];
            // Update phone or link WP user if missing
            $updates = [];
            if ($wpUserId && empty($existing['wp_user_id'])) {
                $updates['wp_user_id'] = $wpUserId;
            }
            if (!empty($data['phone'])) {
                $updates['phone'] = sanitize_text_field($data['phone']);
            }
            if (!empty($updates)) {
                $updates['updated_at'] = gmdate('Y-m-d H:i:s');
                $wpdb->update($table, $updates, ['id' => $customerId]);
            }
            return $customerId;
        }

        return $this->save([
            'first_name' => $data['first_name'] ?? '',
            'last_name' => $data['last_name'] ?? '',
            'email' => $email,
            'phone' => $data['phone'] ?? '',
            'wp_user_id' => $wpUserId,
        ]);
    }

    /**
     * Save customer.
     *
     * @param array<string, mixed> $data
     * @param int|null $id
     * @return int Customer ID
     */
    public function save(array $data, ?int $id = null): int {
        global $wpdb;
        $table = $wpdb->prefix . 'appointpro_customers';
        $now = gmdate('Y-m-d H:i:s');

        $record = [
            'wp_user_id' => !empty($data['wp_user_id']) ? (int) $data['wp_user_id'] : null,
            'first_name' => sanitize_text_field($data['first_name'] ?? ''),
            'last_name' => sanitize_text_field($data['last_name'] ?? ''),
            'email' => sanitize_email($data['email'] ?? ''),
            'phone' => sanitize_text_field($data['phone'] ?? ''),
            'notes' => sanitize_textarea_field($data['notes'] ?? ''),
            'locale' => sanitize_text_field($data['locale'] ?? 'en_US'),
            'updated_at' => $now,
        ];

        if ($id && $id > 0) {
            $wpdb->update($table, $record, ['id' => $id]);
            return $id;
        }

        $record['created_at'] = $now;
        $wpdb->insert($table, $record);
        return (int) $wpdb->insert_id;
    }

    /**
     * Get aggregate statistics for a customer.
     *
     * @param int $customerId
     * @return array{total_bookings: int, completed: int, cancelled: int, total_spent: float}
     */
    public function getCustomerStats(int $customerId): array {
        global $wpdb;
        $custTable = $wpdb->prefix . 'appointpro_appointment_customers';
        $apptTable = $wpdb->prefix . 'appointpro_appointments';

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(a.id) AS total_bookings,
                SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) AS completed,
                SUM(CASE WHEN a.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled,
                COALESCE(SUM(a.paid_amount), 0) AS total_spent
             FROM `{$custTable}` ac
             INNER JOIN `{$apptTable}` a ON ac.appointment_id = a.id
             WHERE ac.customer_id = %d",
            $customerId
        ), ARRAY_A);

        return [
            'total_bookings' => (int) ($row['total_bookings'] ?? 0),
            'completed' => (int) ($row['completed'] ?? 0),
            'cancelled' => (int) ($row['cancelled'] ?? 0),
            'total_spent' => (float) ($row['total_spent'] ?? 0.0),
        ];
    }

    /**
     * Get appointment history for customer.
     *
     * @param int $customerId
     * @param int $limit
     * @return array<int, array<string, mixed>>
     */
    public function getAppointments(int $customerId, int $limit = 50): array {
        global $wpdb;
        $custTable = $wpdb->prefix . 'appointpro_appointment_customers';
        $apptTable = $wpdb->prefix . 'appointpro_appointments';
        $svcTable = $wpdb->prefix . 'appointpro_services';
        $staffTable = $wpdb->prefix . 'appointpro_staff';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, s.name AS service_name, s.color AS service_color, st.full_name AS staff_name
             FROM `{$custTable}` ac
             INNER JOIN `{$apptTable}` a ON ac.appointment_id = a.id
             LEFT JOIN `{$svcTable}` s ON a.service_id = s.id
             LEFT JOIN `{$staffTable}` st ON a.staff_id = st.id
             WHERE ac.customer_id = %d
             ORDER BY a.start_time DESC
             LIMIT %d",
            $customerId,
            $limit
        ), ARRAY_A) ?: [];
    }
}
