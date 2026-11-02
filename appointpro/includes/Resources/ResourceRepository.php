<?php
declare(strict_types=1);

namespace AppointPro\Resources;

/**
 * Shared Resources and Conflict Prevention Repository.
 */
class ResourceRepository {
    /**
     * Get all resources.
     *
     * @param bool $activeOnly
     * @return array<int, array<string, mixed>>
     */
    public function getAll(bool $activeOnly = true): array {
        global $wpdb;
        $table = $wpdb->prefix . 'appointpro_resources';
        $where = $activeOnly ? "WHERE status = 'active'" : "";
        return $wpdb->get_results("SELECT * FROM `{$table}` {$where} ORDER BY name ASC", ARRAY_A) ?: [];
    }

    /**
     * Find resource by ID.
     *
     * @param int $id
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array {
        global $wpdb;
        $table = $wpdb->prefix . 'appointpro_resources';
        $res = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$table}` WHERE id = %d LIMIT 1", $id), ARRAY_A);
        if ($res) {
            $res['service_ids'] = $this->getServiceIdsForResource($id);
        }
        return $res;
    }

    /**
     * Save resource.
     *
     * @param array<string, mixed> $data
     * @param int|null $id
     * @return int Resource ID
     */
    public function save(array $data, ?int $id = null): int {
        global $wpdb;
        $table = $wpdb->prefix . 'appointpro_resources';
        $now = gmdate('Y-m-d H:i:s');

        $record = [
            'name' => sanitize_text_field($data['name'] ?? ''),
            'type' => sanitize_text_field($data['type'] ?? 'room'),
            'capacity' => max(1, (int) ($data['capacity'] ?? 1)),
            'status' => in_array($data['status'] ?? '', ['active', 'maintenance', 'inactive'], true) ? $data['status'] : 'active',
            'updated_at' => $now,
        ];

        if ($id && $id > 0) {
            $wpdb->update($table, $record, ['id' => $id]);
            $resourceId = $id;
        } else {
            $record['created_at'] = $now;
            $wpdb->insert($table, $record);
            $resourceId = (int) $wpdb->insert_id;
        }

        if (isset($data['service_ids']) && is_array($data['service_ids'])) {
            $this->syncServiceAssignments($resourceId, array_map('intval', $data['service_ids']));
        }

        return $resourceId;
    }

    /**
     * Delete resource.
     *
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'appointpro_resources';
        return (bool) $wpdb->update($table, ['status' => 'inactive'], ['id' => $id]);
    }

    /**
     * Get service IDs requiring this resource.
     *
     * @param int $resourceId
     * @return array<int>
     */
    public function getServiceIdsForResource(int $resourceId): array {
        global $wpdb;
        $table = $wpdb->prefix . 'appointpro_resource_services';
        $col = $wpdb->get_col($wpdb->prepare("SELECT service_id FROM `{$table}` WHERE resource_id = %d", $resourceId));
        return array_map('intval', $col ?: []);
    }

    /**
     * Get resource required by a service.
     *
     * @param int $serviceId
     * @return array<string, mixed>|null
     */
    public function getResourceForService(int $serviceId): ?array {
        global $wpdb;
        $resTable = $wpdb->prefix . 'appointpro_resources';
        $linkTable = $wpdb->prefix . 'appointpro_resource_services';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT r.* FROM `{$resTable}` r
             INNER JOIN `{$linkTable}` rs ON r.id = rs.resource_id
             WHERE rs.service_id = %d AND r.status = 'active'
             LIMIT 1",
            $serviceId
        ), ARRAY_A);
    }

    /**
     * Sync services mapped to this resource.
     *
     * @param int $resourceId
     * @param array<int> $serviceIds
     * @return void
     */
    public function syncServiceAssignments(int $resourceId, array $serviceIds): void {
        global $wpdb;
        $table = $wpdb->prefix . 'appointpro_resource_services';
        $wpdb->delete($table, ['resource_id' => $resourceId]);

        foreach ($serviceIds as $svcId) {
            if ($svcId > 0) {
                $wpdb->insert($table, [
                    'resource_id' => $resourceId,
                    'service_id' => $svcId,
                ]);
            }
        }
    }

    /**
     * Check if a resource is available during a given UTC time interval.
     * Prevents simultaneous over-allocation beyond resource capacity.
     *
     * @param int $resourceId
     * @param string $startTime UTC "Y-m-d H:i:s"
     * @param string $endTme UTC "Y-m-d H:i:s"
     * @param int|null $excludeAppointmentId
     * @return bool
     */
    public function isResourceAvailable(int $resourceId, string $startTime, string $endTime, ?int $excludeAppointmentId = null): bool {
        global $wpdb;
        $resTable = $wpdb->prefix . 'appointpro_resources';
        $allocTable = $wpdb->prefix . 'appointpro_resource_allocations';

        $capacity = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT capacity FROM `{$resTable}` WHERE id = %d AND status = 'active'",
            $resourceId
        ));

        if ($capacity <= 0) {
            return false;
        }

        $query = "SELECT COUNT(*) FROM `{$allocTable}`
                  WHERE resource_id = %d
                  AND (start_time < %s AND end_time > %s)";

        $params = [$resourceId, $endTime, $startTime];

        if ($excludeAppointmentId) {
            $query .= " AND appointment_id != %d";
            $params[] = $excludeAppointmentId;
        }

        $activeAllocations = (int) $wpdb->get_var($wpdb->prepare($query, ...$params));

        return $activeAllocations < $capacity;
    }

    /**
     * Allocate resource to an appointment.
     *
     * @param int $appointmentId
     * @param int $resourceId
     * @param string $startTime UTC
     * @param string $endTime UTC
     * @return bool
     */
    public function allocate(int $appointmentId, int $resourceId, string $startTime, string $endTime): bool {
        global $wpdb;
        $table = $wpdb->prefix . 'appointpro_resource_allocations';

        return (bool) $wpdb->insert($table, [
            'appointment_id' => $appointmentId,
            'resource_id' => $resourceId,
            'start_time' => $startTime,
            'end_time' => $endTime,
        ]);
    }

    /**
     * Release resource allocations for an appointment.
     *
     * @param int $appointmentId
     * @return void
     */
    public function release(int $appointmentId): void {
        global $wpdb;
        $table = $wpdb->prefix . 'appointpro_resource_allocations';
        $wpdb->delete($table, ['appointment_id' => $appointmentId]);
    }
}
