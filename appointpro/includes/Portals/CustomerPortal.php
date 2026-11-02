<?php
declare(strict_types=1);

namespace AppointPro\Portals;

use AppointPro\Customers\CustomerRepository;
use AppointPro\Helpers\Currency;
use AppointPro\Helpers\DateTimeHelper;
use AppointPro\Helpers\Formatting;

/**
 * Customer Self-Service Portal: Allows customers to manage bookings, reschedule, cancel, and view receipts.
 */
class CustomerPortal {
    /**
     * Render the customer portal shortcode.
     *
     * @param array<string, mixed> $atts
     * @return string HTML output
     */
    public static function render(array $atts = []): string {
        if (!is_user_logged_in()) {
            return sprintf(
                '<div class="appointpro-portal-notice"><p>%s <a href="%s">%s</a></p></div>',
                esc_html__('Please sign in to access your appointment portal.', 'appointpro'),
                esc_url(wp_login_url(get_permalink())),
                esc_html__('Log in here', 'appointpro')
            );
        }

        $userId = get_current_user_id();
        global $wpdb;
        $custTable = $wpdb->prefix . 'appointpro_customers';
        $customer = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$custTable}` WHERE wp_user_id = %d LIMIT 1", $userId), ARRAY_A);

        if (!$customer) {
            // Check by email
            $user = wp_get_current_user();
            $customer = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$custTable}` WHERE email = %s LIMIT 1", $user->user_email), ARRAY_A);
            if ($customer) {
                // Link account
                $wpdb->update($custTable, ['wp_user_id' => $userId], ['id' => $customer['id']]);
            }
        }

        if (!$customer) {
            return '<div class="appointpro-portal-notice"><p>' . esc_html__('No customer profile associated with this account.', 'appointpro') . '</p></div>';
        }

        $custRepo = new CustomerRepository();
        $appointments = $custRepo->getAppointments((int) $customer['id']);

        ob_start();
        ?>
        <div class="appointpro-customer-portal">
            <div class="appointpro-portal-header">
                <h2><?php printf(esc_html__('Welcome, %s', 'appointpro'), esc_html($customer['first_name'])); ?></h2>
                <p><?php esc_html_e('Manage your upcoming and past appointments.', 'appointpro'); ?></p>
            </div>

            <?php if (empty($appointments)): ?>
                <div class="appointpro-empty-state">
                    <p><?php esc_html_e('You have no appointments booked yet.', 'appointpro'); ?></p>
                </div>
            <?php else: ?>
                <div class="appointpro-appointments-list">
                    <table class="appointpro-portal-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Reference', 'appointpro'); ?></th>
                                <th><?php esc_html_e('Service', 'appointpro'); ?></th>
                                <th><?php esc_html_e('Staff', 'appointpro'); ?></th>
                                <th><?php esc_html_e('Date & Time', 'appointpro'); ?></th>
                                <th><?php esc_html_e('Status', 'appointpro'); ?></th>
                                <th><?php esc_html_e('Price', 'appointpro'); ?></th>
                                <th><?php esc_html_e('Actions', 'appointpro'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appointments as $appt): ?>
                                <tr>
                                    <td><strong><?php echo esc_html($appt['booking_reference']); ?></strong></td>
                                    <td><?php echo esc_html($appt['service_name'] ?? __('Service', 'appointpro')); ?></td>
                                    <td><?php echo esc_html($appt['staff_name'] ?? __('Staff', 'appointpro')); ?></td>
                                    <td><?php echo esc_html(DateTimeHelper::formatForDisplay($appt['start_time'])); ?></td>
                                    <td>
                                        <span class="appointpro-badge appointpro-status-<?php echo esc_attr($appt['status']); ?>">
                                            <?php echo esc_html(ucfirst($appt['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html(Currency::format((float) $appt['final_price'])); ?></td>
                                    <td>
                                        <?php if ($appt['status'] === 'confirmed' || $appt['status'] === 'pending'): ?>
                                            <button type="button" class="appointpro-btn appointpro-btn-sm appointpro-cancel-btn" data-id="<?php echo (int) $appt['id']; ?>">
                                                <?php esc_html_e('Cancel', 'appointpro'); ?>
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
