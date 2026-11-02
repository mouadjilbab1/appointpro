<?php
declare(strict_types=1);

namespace AppointPro\Portals;

use AppointPro\Helpers\Currency;
use AppointPro\Helpers\DateTimeHelper;
use AppointPro\Permissions\PermissionsManager;

/**
 * Staff Self-Service Portal: Lets staff members view their daily schedule, manage appointments, and update statuses.
 */
class StaffPortal {
    /**
     * Render the staff portal shortcode.
     *
     * @param array<string, mixed> $atts
     * @return string
     */
    public static function render(array $atts = []): string {
        if (!is_user_logged_in()) {
            return sprintf(
                '<div class="appointpro-portal-notice"><p>%s <a href="%s">%s</a></p></div>',
                esc_html__('Please sign in to access the staff portal.', 'appointpro'),
                esc_url(wp_login_url(get_permalink())),
                esc_html__('Log in here', 'appointpro')
            );
        }

        $userId = get_current_user_id();
        global $wpdb;
        $staffTable = $wpdb->prefix . 'appointpro_staff';
        $staff = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$staffTable}` WHERE wp_user_id = %d AND status = 'active' LIMIT 1", $userId), ARRAY_A);

        if (!$staff && !current_user_can('manage_options')) {
            return '<div class="appointpro-portal-notice"><p>' . esc_html__('Your account is not registered as an active staff member.', 'appointpro') . '</p></div>';
        }

        $staffId = $staff ? (int) $staff['id'] : 0;
        $apptTable = $wpdb->prefix . 'appointpro_appointments';
        $svcTable = $wpdb->prefix . 'appointpro_services';
        $custTable = $wpdb->prefix . 'appointpro_customers';
        $acTable = $wpdb->prefix . 'appointpro_appointment_customers';

        $query = "SELECT a.*, s.name AS service_name, c.first_name, c.last_name, c.email, c.phone
                  FROM `{$apptTable}` a
                  LEFT JOIN `{$svcTable}` s ON a.service_id = s.id
                  LEFT JOIN `{$acTable}` ac ON a.id = ac.appointment_id
                  LEFT JOIN `{$custTable}` c ON ac.customer_id = c.id";

        if ($staffId > 0) {
            $query .= $wpdb->prepare(" WHERE a.staff_id = %d", $staffId);
        }

        $query .= " ORDER BY a.start_time DESC LIMIT 50";
        $appointments = $wpdb->get_results($query, ARRAY_A) ?: [];

        ob_start();
        ?>
        <div class="appointpro-staff-portal">
            <div class="appointpro-portal-header">
                <h2><?php printf(esc_html__('Staff Dashboard – %s', 'appointpro'), esc_html($staff['full_name'] ?? 'Admin')); ?></h2>
                <p><?php esc_html_e('Review your assigned appointments and client information.', 'appointpro'); ?></p>
            </div>

            <?php if (empty($appointments)): ?>
                <div class="appointpro-empty-state">
                    <p><?php esc_html_e('No assigned appointments found.', 'appointpro'); ?></p>
                </div>
            <?php else: ?>
                <table class="appointpro-portal-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Time', 'appointpro'); ?></th>
                            <th><?php esc_html_e('Client', 'appointpro'); ?></th>
                            <th><?php esc_html_e('Service', 'appointpro'); ?></th>
                            <th><?php esc_html_e('Contact', 'appointpro'); ?></th>
                            <th><?php esc_html_e('Status', 'appointpro'); ?></th>
                            <th><?php esc_html_e('Actions', 'appointpro'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($appointments as $appt): ?>
                            <tr>
                                <td><?php echo esc_html(DateTimeHelper::formatForDisplay($appt['start_time'])); ?></td>
                                <td><strong><?php echo esc_html(($appt['first_name'] ?? '') . ' ' . ($appt['last_name'] ?? '')); ?></strong></td>
                                <td><?php echo esc_html($appt['service_name'] ?? __('Service', 'appointpro')); ?></td>
                                <td>
                                    <div><?php echo esc_html($appt['email'] ?? ''); ?></div>
                                    <small><?php echo esc_html($appt['phone'] ?? ''); ?></small>
                                </td>
                                <td>
                                    <span class="appointpro-badge appointpro-status-<?php echo esc_attr($appt['status']); ?>">
                                        <?php echo esc_html(ucfirst($appt['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($appt['status'] === 'pending'): ?>
                                        <button class="appointpro-btn appointpro-btn-sm appointpro-btn-success" data-action="confirm" data-id="<?php echo (int) $appt['id']; ?>">
                                            <?php esc_html_e('Confirm', 'appointpro'); ?>
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($appt['status'] === 'confirmed'): ?>
                                        <button class="appointpro-btn appointpro-btn-sm" data-action="complete" data-id="<?php echo (int) $appt['id']; ?>">
                                            <?php esc_html_e('Complete', 'appointpro'); ?>
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
