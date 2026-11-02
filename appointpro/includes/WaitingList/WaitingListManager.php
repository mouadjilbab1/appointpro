<?php
declare(strict_types=1);

namespace AppointPro\WaitingList;

use AppointPro\Notifications\NotificationDispatcher;

/**
 * Waiting List Management and Slot Notification Manager.
 */
class WaitingListManager {
    /**
     * Add customer to waiting list for a full day/slot.
     *
     * @param int $serviceId
     * @param int $customerId
     * @param string $desiredDate "Y-m-d"
     * @param int|null $staffId
     * @return int Waiting list entry ID
     */
    public function addToWaitingList(int $serviceId, int $customerId, string $desiredDate, ?int $staffId = null): int {
        global $wpdb;
        $table = $wpdb->prefix . 'appointpro_waiting_list';

        $wpdb->insert($table, [
            'service_id' => $serviceId,
            'staff_id' => $staffId,
            'customer_id' => $customerId,
            'desired_date' => $desiredDate,
            'status' => 'waiting',
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        return (int) $wpdb->insert_id;
    }

    /**
     * Notify next waiting customer when an appointment is cancelled on a given date.
     *
     * @param int $serviceId
     * @param string $date "Y-m-d"
     * @param int|null $staffId
     * @return bool True if a customer was notified
     */
    public function notifyNextInLine(int $serviceId, string $date, ?int $staffId = null): bool {
        global $wpdb;
        $waitTable = $wpdb->prefix . 'appointpro_waiting_list';
        $custTable = $wpdb->prefix . 'appointpro_customers';

        $entry = $wpdb->get_row($wpdb->prepare(
            "SELECT w.*, c.email, c.first_name, c.last_name
             FROM `{$waitTable}` w
             INNER JOIN `{$custTable}` c ON w.customer_id = c.id
             WHERE w.service_id = %d AND w.desired_date = %s AND w.status = 'waiting'
             ORDER BY w.created_at ASC
             LIMIT 1",
            $serviceId,
            $date
        ), ARRAY_A);

        if (!$entry) {
            return false;
        }

        // Give 2 hours to claim before offering to next in line
        $expiresAt = gmdate('Y-m-d H:i:s', strtotime('+2 hours'));

        $wpdb->update($waitTable, [
            'status' => 'notified',
            'offer_expires_at' => $expiresAt,
        ], ['id' => $entry['id']]);

        // Dispatch notification
        $dispatcher = new NotificationDispatcher();
        wp_mail(
            $entry['email'],
            __('A booking slot has opened up for you!', 'appointpro'),
            sprintf(
                __("Dear %s,\n\nA time slot is now available on %s for the service you requested. Please log in within 2 hours to claim your booking.", 'appointpro'),
                $entry['first_name'],
                $date
            )
        );

        return true;
    }
}
