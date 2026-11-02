<?php
declare(strict_types=1);

namespace AppointPro\Services;

/**
 * Service and Category Repository.
 */
class ServiceRepository {
    /**
     * Get all active categories with their services.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getCategoriesWithServices(): array {
        global $wpdb;
        $catTable = $wpdb->prefix . 'appointpro_categories';
        $svcTable = $wpdb->prefix . 'appointpro_services';

        $categories = $wpdb->get_results(
            "SELECT * FROM `{$catTable}` ORDER BY position ASC, name ASC",
            ARRAY_A
        );

        $services = $wpdb->get_results(
            "SELECT * FROM `{$svcTable}` WHERE status = 'active' ORDER BY position ASC, name ASC",
            ARRAY_A
        );

        $categorized = [];
        $byCat = [];
        foreach ($services as $svc) {
            $catId = (int) ($svc['category_id'] ?? 0);
            $svc['extras'] = $this->getServiceExtras((int) $svc['id']);
            $byCat[$catId][] = $svc;
        }

        foreach ($categories as $cat) {
            $catId = (int) $cat['id'];
            $cat['services'] = $byCat[$catId] ?? [];
            $categorized[] = $cat;
        }

        // Add uncategorized if any
        if (!empty($byCat[0])) {
            $categorized[] = [
                'id' => 0,
                'name' => __('Uncategorized', 'appointpro'),
                'description' => '',
                'color' => '#6b7280',
                'services' => $byCat[0],
            ];
        }

        return $categorized;
    }

    /**
     * Find service by ID.
     *
     * @param int $id
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array {
        global $wpdb;
        $svcTable = $wpdb->prefix . 'appointpro_services';
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM `{$svcTable}` WHERE id = %d LIMIT 1", $id),
            ARRAY_A
        );

        if (!$row) {
            return null;
        }

        $row['extras'] = $this->getServiceExtras($id);
        $row['staff_ids'] = $this->getServiceStaffIds($id);
        return $row;
    }

    /**
     * Create or update a service.
     *
     * @param array<string, mixed> $data
     * @param int|null $id
     * @return int Service ID
     */
    public function save(array $data, ?int $id = null): int {
        global $wpdb;
        $svcTable = $wpdb->prefix . 'appointpro_services';
        $now = gmdate('Y-m-d H:i:s');

        $record = [
            'category_id' => !empty($data['category_id']) ? (int) $data['category_id'] : null,
            'name' => sanitize_text_field($data['name'] ?? ''),
            'description' => wp_kses_post($data['description'] ?? ''),
            'duration' => max(1, (int) ($data['duration'] ?? 30)),
            'price' => (float) ($data['price'] ?? 0.00),
            'deposit_type' => in_array($data['deposit_type'] ?? '', ['none', 'fixed', 'percentage'], true) ? $data['deposit_type'] : 'none',
            'deposit_amount' => (float) ($data['deposit_amount'] ?? 0.00),
            'buffer_before' => max(0, (int) ($data['buffer_before'] ?? 0)),
            'buffer_after' => max(0, (int) ($data['buffer_after'] ?? 0)),
            'min_notice_hours' => max(0, (int) ($data['min_notice_hours'] ?? 2)),
            'max_advance_days' => max(1, (int) ($data['max_advance_days'] ?? 90)),
            'capacity_min' => max(1, (int) ($data['capacity_min'] ?? 1)),
            'capacity_max' => max(1, (int) ($data['capacity_max'] ?? 1)),
            'color' => sanitize_hex_color($data['color'] ?? '') ?: '#3b82f6',
            'image_url' => esc_url_raw($data['image_url'] ?? ''),
            'requires_approval' => !empty($data['requires_approval']) ? 1 : 0,
            'allow_cancellation' => isset($data['allow_cancellation']) ? (int) $data['allow_cancellation'] : 1,
            'cancellation_deadline_hours' => max(0, (int) ($data['cancellation_deadline_hours'] ?? 24)),
            'allow_rescheduling' => isset($data['allow_rescheduling']) ? (int) $data['allow_rescheduling'] : 1,
            'is_recurring_enabled' => !empty($data['is_recurring_enabled']) ? 1 : 0,
            'status' => in_array($data['status'] ?? '', ['active', 'hidden', 'archived'], true) ? $data['status'] : 'active',
            'position' => (int) ($data['position'] ?? 0),
            'updated_at' => $now,
        ];

        if ($id && $id > 0) {
            $wpdb->update($svcTable, $record, ['id' => $id]);
            $serviceId = $id;
        } else {
            $record['created_at'] = $now;
            $wpdb->insert($svcTable, $record);
            $serviceId = (int) $wpdb->insert_id;
        }

        // Sync staff assignments if provided
        if (isset($data['staff_ids']) && is_array($data['staff_ids'])) {
            $this->syncStaffAssignments($serviceId, array_map('intval', $data['staff_ids']));
        }

        // Sync extras if provided
        if (isset($data['extras']) && is_array($data['extras'])) {
            $this->syncExtras($serviceId, $data['extras']);
        }

        return $serviceId;
    }

    /**
     * Delete or archive a service.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool {
        global $wpdb;
        $svcTable = $wpdb->prefix . 'appointpro_services';
        // Mark as archived rather than hard deleting to preserve historical appointment integrity
        return (bool) $wpdb->update($svcTable, ['status' => 'archived'], ['id' => $id]);
    }

    /**
     * Get extras for a service.
     *
     * @param int $serviceId
     * @return array<int, array<string, mixed>>
     */
    public function getServiceExtras(int $serviceId): array {
        global $wpdb;
        $table = $wpdb->prefix . 'appointpro_extras';
        return $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM `{$table}` WHERE service_id = %d AND status = 'active' ORDER BY name ASC", $serviceId),
            ARRAY_A
        ) ?: [];
    }

    /**
     * Sync extras list for a service.
     *
     * @param int $serviceId
     * @param array<int, array<string, mixed>> $extras
     * @return void
     */
    public function syncExtras(int $serviceId, array $extras): void {
        global $wpdb;
        $table = $wpdb->prefix . 'appointpro_extras';

        $existingIds = $wpdb->get_col($wpdb->prepare("SELECT id FROM `{$table}` WHERE service_id = %d", $serviceId));
        $keptIds = [];

        foreach ($extras as $extra) {
            $extraId = !empty($extra['id']) ? (int) $extra['id'] : null;
            $data = [
                'service_id' => $serviceId,
                'name' => sanitize_text_field($extra['name'] ?? ''),
                'description' => sanitize_textarea_field($extra['description'] ?? ''),
                'price' => (float) ($extra['price'] ?? 0.00),
                'duration' => max(0, (int) ($extra['duration'] ?? 0)),
                'max_quantity' => max(1, (int) ($extra['max_quantity'] ?? 1)),
                'status' => ($extra['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active',
            ];

            if ($extraId && in_array($extraId, $existingIds, true)) {
                $wpdb->update($table, $data, ['id' => $extraId]);
                $keptIds[] = $extraId;
            } else {
                $wpdb->insert($table, $data);
                $keptIds[] = (int) $wpdb->insert_id;
            }
        }

        // Remove deleted extras
        $toDelete = array_diff($existingIds, $keptIds);
        if (!empty($toDelete)) {
            $inClause = implode(',', array_map('intval', $toDelete));
            $wpdb->query("DELETE FROM `{$table}` WHERE id IN ({$inClause})");
        }
    }

    /**
     * Get staff IDs assigned to this service.
     *
     * @param int $serviceId
     * @return array<int>
     */
    public function getServiceStaffIds(int $serviceId): array {
        global $wpdb;
        $table = $wpdb->prefix . 'appointpro_staff_services';
        $col = $wpdb->get_col($wpdb->prepare("SELECT staff_id FROM `{$table}` WHERE service_id = %d", $serviceId));
        return array_map('intval', $col ?: []);
    }

    /**
     * Sync staff assignments for a service.
     *
     * @param int $serviceId
     * @param array<int> $staffIds
     * @return void
     */
    public function syncStaffAssignments(int $serviceId, array $staffIds): void {
        global $wpdb;
        $table = $wpdb->prefix . 'appointpro_staff_services';

        $wpdb->delete($table, ['service_id' => $serviceId]);

        foreach ($staffIds as $staffId) {
            if ($staffId > 0) {
                $wpdb->insert($table, [
                    'staff_id' => $staffId,
                    'service_id' => $serviceId,
                ]);
            }
        }
    }

    /**
     * Save category.
     *
     * @param array<string, mixed> $data
     * @param int|null $id
     * @return int
     */
    public function saveCategory(array $data, ?int $id = null): int {
        global $wpdb;
        $catTable = $wpdb->prefix . 'appointpro_categories';
        $now = gmdate('Y-m-d H:i:s');

        $record = [
            'name' => sanitize_text_field($data['name'] ?? ''),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'color' => sanitize_hex_color($data['color'] ?? '') ?: '#4f46e5',
            'position' => (int) ($data['position'] ?? 0),
            'updated_at' => $now,
        ];

        if ($id && $id > 0) {
            $wpdb->update($catTable, $record, ['id' => $id]);
            return $id;
        }

        $record['created_at'] = $now;
        $wpdb->insert($catTable, $record);
        return (int) $wpdb->insert_id;
    }

    /**
     * Delete category.
     *
     * @param int $id
     * @return bool
     */
    public function deleteCategory(int $id): bool {
        global $wpdb;
        $catTable = $wpdb->prefix . 'appointpro_categories';
        $svcTable = $wpdb->prefix . 'appointpro_services';

        // Set associated services category_id to NULL
        $wpdb->update($svcTable, ['category_id' => null], ['category_id' => $id]);
        return (bool) $wpdb->delete($catTable, ['id' => $id]);
    }
}
