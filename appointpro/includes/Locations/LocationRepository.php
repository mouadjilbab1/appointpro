<?php
declare(strict_types=1);

namespace AppointPro\Locations;

/**
 * Business Locations Repository.
 */
class LocationRepository {
    /**
     * Get all locations.
     *
     * @param bool $activeOnly
     * @return array<int, array<string, mixed>>
     */
    public function getAll(bool $activeOnly = true): array {
        global $wpdb;
        $table = $wpdb->prefix . 'appointpro_locations';
        $where = $activeOnly ? "WHERE status = 'active'" : "";
        return $wpdb->get_results("SELECT * FROM `{$table}` {$where} ORDER BY name ASC", ARRAY_A) ?: [];
    }

    /**
     * Find location by ID.
     *
     * @param int $id
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'appointpro_locations';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$table}` WHERE id = %d LIMIT 1", $id), ARRAY_A);
    }

    /**
     * Save location.
     *
     * @param array<string, mixed> $data
     * @param int|null $id
     * @return int Location ID
     */
    public function save(array $data, ?int $id = null): int {
        global $wpdb;
        $table = $wpdb->prefix . 'appointpro_locations';
        $now = gmdate('Y-m-d H:i:s');

        $record = [
            'name' => sanitize_text_field($data['name'] ?? ''),
            'address' => sanitize_textarea_field($data['address'] ?? ''),
            'phone' => sanitize_text_field($data['phone'] ?? ''),
            'email' => sanitize_email($data['email'] ?? ''),
            'timezone' => sanitize_text_field($data['timezone'] ?? 'UTC'),
            'status' => in_array($data['status'] ?? '', ['active', 'inactive'], true) ? $data['status'] : 'active',
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
     * Delete location.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'appointpro_locations';
        return (bool) $wpdb->update($table, ['status' => 'inactive'], ['id' => $id]);
    }
}
