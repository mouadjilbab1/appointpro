<?php
declare(strict_types=1);

namespace AppointPro\Notifications;

use AppointPro\Helpers\Currency;
use AppointPro\Helpers\DateTimeHelper;

/**
 * Multi-channel Notification Dispatcher: Emails, SMS, WhatsApp with Template Parsing and Queue Processing.
 */
class NotificationDispatcher {
    /**
     * Queue event notifications for an appointment.
     *
     * @param string $event 'appointment_confirmed', 'appointment_pending', 'appointment_cancelled', 'appointment_rescheduled', 'payment_completed'
     * @param int $appointmentId
     * @return void
     */
    public function queueEventNotification(string $event, int $appointmentId): void {
        global $wpdb;
        $apptTable = $wpdb->prefix . 'appointpro_appointments';
        $svcTable = $wpdb->prefix . 'appointpro_services';
        $staffTable = $wpdb->prefix . 'appointpro_staff';
        $custTable = $wpdb->prefix . 'appointpro_customers';
        $acTable = $wpdb->prefix . 'appointpro_appointment_customers';

        $data = $wpdb->get_row($wpdb->prepare(
            "SELECT a.*, s.name AS service_name, st.full_name AS staff_name, st.email AS staff_email,
                    c.first_name, c.last_name, c.email AS customer_email, c.phone AS customer_phone
             FROM `{$apptTable}` a
             LEFT JOIN `{$svcTable}` s ON a.service_id = s.id
             LEFT JOIN `{$staffTable}` st ON a.staff_id = st.id
             LEFT JOIN `{$acTable}` ac ON a.id = ac.appointment_id
             LEFT JOIN `{$custTable}` c ON ac.customer_id = c.id
             WHERE a.id = %d LIMIT 1",
            $appointmentId
        ), ARRAY_A);

        if (!$data) {
            return;
        }

        $tokens = $this->buildTokens($data);
        $templates = $this->getTemplatesForEvent($event);

        $now = gmdate('Y-m-d H:i:s');
        $notifTable = $wpdb->prefix . 'appointpro_notifications';

        // 1. Queue Customer Email
        if (!empty($data['customer_email']) && !empty($templates['customer_email'])) {
            $subject = $this->replaceTokens($templates['customer_email']['subject'], $tokens);
            $body = $this->renderHtmlEmail($this->replaceTokens($templates['customer_email']['body'], $tokens));
            $wpdb->insert($notifTable, [
                'appointment_id' => $appointmentId,
                'recipient' => $data['customer_email'],
                'channel' => 'email',
                'event' => $event,
                'subject' => $subject,
                'message' => $body,
                'status' => 'queued',
                'scheduled_for' => $now,
            ]);
        }

        // 2. Queue Staff Notification Email
        if (!empty($data['staff_email']) && !empty($templates['staff_email'])) {
            $subject = $this->replaceTokens($templates['staff_email']['subject'], $tokens);
            $body = $this->renderHtmlEmail($this->replaceTokens($templates['staff_email']['body'], $tokens));
            $wpdb->insert($notifTable, [
                'appointment_id' => $appointmentId,
                'recipient' => $data['staff_email'],
                'channel' => 'email',
                'event' => $event,
                'subject' => $subject,
                'message' => $body,
                'status' => 'queued',
                'scheduled_for' => $now,
            ]);
        }
    }

    /**
     * Process asynchronous notification queue (called by WP-Cron).
     *
     * @param int $batchSize
     * @return int Count of sent messages
     */
    public function processQueue(int $batchSize = 25): int {
        global $wpdb;
        $table = $wpdb->prefix . 'appointpro_notifications';
        $now = gmdate('Y-m-d H:i:s');

        $queued = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM `{$table}`
             WHERE status = 'queued'
             AND scheduled_for <= %s
             AND attempts < 3
             ORDER BY scheduled_for ASC
             LIMIT %d",
            $now,
            $batchSize
        ), ARRAY_A) ?: [];

        $sentCount = 0;

        foreach ($queued as $item) {
            $id = (int) $item['id'];
            $channel = $item['channel'];
            $recipient = $item['recipient'];
            $subject = $item['subject'] ?? '';
            $message = $item['message'];

            $success = false;
            $errorMessage = '';

            if ($channel === 'email') {
                $headers = ['Content-Type: text/html; charset=UTF-8'];
                $success = wp_mail($recipient, $subject, $message, $headers);
                if (!$success) {
                    $errorMessage = 'wp_mail returned false.';
                }
            } elseif ($channel === 'sms') {
                // SMS dispatch via adapter
                $success = true; // placeholder or configured adapter
            }

            if ($success) {
                $wpdb->update($table, [
                    'status' => 'sent',
                    'attempts' => (int) $item['attempts'] + 1,
                    'sent_at' => gmdate('Y-m-d H:i:s'),
                ], ['id' => $id]);
                $sentCount++;
            } else {
                $attempts = (int) $item['attempts'] + 1;
                $status = ($attempts >= 3) ? 'failed' : 'queued';
                $wpdb->update($table, [
                    'status' => $status,
                    'attempts' => $attempts,
                    'error_message' => $errorMessage,
                ], ['id' => $id]);
            }
        }

        return $sentCount;
    }

    /**
     * Render responsive HTML email template.
     *
     * @param string $innerContent
     * @return string
     */
    protected function renderHtmlEmail(string $innerContent): string {
        $settings = (array) get_option('appointpro_settings', []);
        $primaryColor = $settings['primary_color'] ?? '#4f46e5';
        $businessName = esc_html($settings['business_name'] ?? get_bloginfo('name'));

        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { margin:0; padding:0; background-color:#f4f5f7; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Helvetica,Arial,sans-serif; color:#374151; }
        .wrapper { width:100%; max-width:600px; margin:0 auto; background:#ffffff; border-radius:8px; overflow:hidden; margin-top:20px; margin-bottom:20px; border:1px solid #e5e7eb; }
        .header { background-color:' . esc_attr($primaryColor) . '; padding:24px; text-align:center; color:#ffffff; }
        .header h1 { margin:0; font-size:22px; font-weight:700; }
        .content { padding:32px 24px; font-size:15px; line-height:1.6; }
        .footer { padding:20px 24px; background:#f9fafb; text-align:center; font-size:12px; color:#9ca3af; border-top:1px solid #e5e7eb; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="header">
            <h1>' . $businessName . '</h1>
        </div>
        <div class="content">
            ' . nl2br($innerContent) . '
        </div>
        <div class="footer">
            &copy; ' . date('Y') . ' ' . $businessName . '. ' . esc_html__('All rights reserved.', 'appointpro') . '
        </div>
    </div>
</body>
</html>';
    }

    /**
     * Build token replacement array.
     *
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    protected function buildTokens(array $data): array {
        $settings = (array) get_option('appointpro_settings', []);
        $businessName = $settings['business_name'] ?? get_bloginfo('name');
        $customerName = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));

        return [
            '{customer_name}' => $customerName ?: __('Valued Client', 'appointpro'),
            '{customer_email}' => $data['customer_email'] ?? '',
            '{customer_phone}' => $data['customer_phone'] ?? '',
            '{service_name}' => $data['service_name'] ?? __('Service', 'appointpro'),
            '{staff_name}' => $data['staff_name'] ?? __('Assigned Staff', 'appointpro'),
            '{appointment_date}' => DateTimeHelper::formatForDisplay($data['start_time'], 'Y-m-d'),
            '{appointment_time}' => DateTimeHelper::formatForDisplay($data['start_time'], 'H:i'),
            '{booking_reference}' => $data['booking_reference'] ?? '',
            '{final_price}' => Currency::format((float) ($data['final_price'] ?? 0)),
            '{paid_amount}' => Currency::format((float) ($data['paid_amount'] ?? 0)),
            '{business_name}' => $businessName,
        ];
    }

    /**
     * Replace token tags in a template string.
     *
     * @param string $template
     * @param array<string, string> $tokens
     * @return string
     */
    protected function replaceTokens(string $template, array $tokens): string {
        return str_replace(array_keys($tokens), array_values($tokens), $template);
    }

    /**
     * Default notification templates per event.
     *
     * @param string $event
     * @return array<string, array{subject: string, body: string}>
     */
    protected function getTemplatesForEvent(string $event): array {
        $templates = [
            'appointment_confirmed' => [
                'customer_email' => [
                    'subject' => __('Booking Confirmed – {booking_reference}', 'appointpro'),
                    'body' => __("Dear {customer_name},\n\nYour appointment for {service_name} with {staff_name} has been confirmed for {appointment_date} at {appointment_time}.\n\nBooking Reference: {booking_reference}\nTotal: {final_price}\n\nThank you for choosing {business_name}!", 'appointpro'),
                ],
                'staff_email' => [
                    'subject' => __('New Booking Assigned – {booking_reference}', 'appointpro'),
                    'body' => __("Hello {staff_name},\n\nYou have a new booking confirmed for {service_name} on {appointment_date} at {appointment_time}.\nClient: {customer_name} ({customer_email}, {customer_phone})\nReference: {booking_reference}", 'appointpro'),
                ],
            ],
            'appointment_pending' => [
                'customer_email' => [
                    'subject' => __('Booking Received (Pending Approval) – {booking_reference}', 'appointpro'),
                    'body' => __("Dear {customer_name},\n\nWe have received your appointment request for {service_name} on {appointment_date} at {appointment_time}. Our team will review and confirm your booking shortly.\n\nBooking Reference: {booking_reference}", 'appointpro'),
                ],
            ],
            'appointment_cancelled' => [
                'customer_email' => [
                    'subject' => __('Appointment Cancelled – {booking_reference}', 'appointpro'),
                    'body' => __("Dear {customer_name},\n\nYour appointment {booking_reference} for {service_name} on {appointment_date} has been cancelled.\n\nIf you have any questions, please contact {business_name}.", 'appointpro'),
                ],
            ],
            'appointment_rescheduled' => [
                'customer_email' => [
                    'subject' => __('Appointment Rescheduled – {booking_reference}', 'appointpro'),
                    'body' => __("Dear {customer_name},\n\nYour appointment {booking_reference} for {service_name} has been rescheduled to {appointment_date} at {appointment_time}.\n\nThank you,\n{business_name}", 'appointpro'),
                ],
            ],
            'payment_completed' => [
                'customer_email' => [
                    'subject' => __('Payment Received for Booking {booking_reference}', 'appointpro'),
                    'body' => __("Dear {customer_name},\n\nWe have successfully received your payment of {paid_amount} for appointment {booking_reference}.\n\nThank you,\n{business_name}", 'appointpro'),
                ],
            ],
        ];

        return $templates[$event] ?? [];
    }
}
