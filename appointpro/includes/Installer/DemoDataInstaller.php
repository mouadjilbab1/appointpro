<?php
declare(strict_types=1);

namespace AppointPro\Installer;

/**
 * Sample Demo Data Installer for Testing and Demonstration.
 */
class DemoDataInstaller {
    /**
     * Install sample categories, services, staff, locations, and schedules.
     *
     * @return void
     */
    public static function installDemoData(): void {
        global $wpdb;
        $now = gmdate('Y-m-d H:i:s');

        // 1. Categories
        $catTable = $wpdb->prefix . 'appointpro_categories';
        $wpdb->insert($catTable, [
            'name' => 'Hair & Styling',
            'description' => 'Haircuts, styling and coloring services',
            'color' => '#4f46e5',
            'position' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $catHairId = (int) $wpdb->insert_id;

        $wpdb->insert($catTable, [
            'name' => 'Wellness & Spa',
            'description' => 'Massage, facial treatments and relaxation',
            'color' => '#10b981',
            'position' => 2,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $catSpaId = (int) $wpdb->insert_id;

        // 2. Services
        $svcTable = $wpdb->prefix . 'appointpro_services';
        $wpdb->insert($svcTable, [
            'category_id' => $catHairId,
            'name' => 'Master Executive Haircut',
            'description' => 'Precision haircut with shampoo, scalp massage and styling.',
            'duration' => 45,
            'price' => 45.00,
            'deposit_type' => 'none',
            'buffer_before' => 5,
            'buffer_after' => 10,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $svcHaircutId = (int) $wpdb->insert_id;

        $wpdb->insert($svcTable, [
            'category_id' => $catSpaId,
            'name' => 'Deep Tissue Aromatherapy Massage',
            'description' => '60-minute full body massage using therapeutic essential oils.',
            'duration' => 60,
            'price' => 85.00,
            'deposit_type' => 'percentage',
            'deposit_amount' => 20.00,
            'buffer_before' => 10,
            'buffer_after' => 15,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $svcMassageId = (int) $wpdb->insert_id;

        // 3. Staff
        $staffTable = $wpdb->prefix . 'appointpro_staff';
        $wpdb->insert($staffTable, [
            'full_name' => 'Sarah Jenkins',
            'email' => 'sarah.jenkins@example.com',
            'phone' => '+1 (555) 234-5678',
            'bio' => 'Senior hair stylist with 8 years of salon experience.',
            'auto_assign' => 1,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $staffSarahId = (int) $wpdb->insert_id;

        $wpdb->insert($staffTable, [
            'full_name' => 'David Miller',
            'email' => 'david.miller@example.com',
            'phone' => '+1 (555) 876-5432',
            'bio' => 'Certified massage therapist specializing in sports rehabilitation.',
            'auto_assign' => 1,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $staffDavidId = (int) $wpdb->insert_id;

        // Map Staff to Services
        $staffSvcTable = $wpdb->prefix . 'appointpro_staff_services';
        $wpdb->insert($staffSvcTable, ['staff_id' => $staffSarahId, 'service_id' => $svcHaircutId]);
        $wpdb->insert($staffSvcTable, ['staff_id' => $staffDavidId, 'service_id' => $svcMassageId]);

        // 4. Default Schedules (Mon-Fri 09:00 - 17:00)
        $schedTable = $wpdb->prefix . 'appointpro_schedules';
        $breakTable = $wpdb->prefix . 'appointpro_schedule_breaks';

        foreach ([$staffSarahId, $staffDavidId] as $stId) {
            for ($d = 1; $d <= 5; $d++) {
                $wpdb->insert($schedTable, [
                    'staff_id' => $stId,
                    'day_of_week' => $d,
                    'start_time' => '09:00:00',
                    'end_time' => '17:00:00',
                    'is_working' => 1,
                ]);
                $schedId = (int) $wpdb->insert_id;
                $wpdb->insert($breakTable, [
                    'schedule_id' => $schedId,
                    'start_time' => '12:30:00',
                    'end_time' => '13:30:00',
                    'label' => 'Lunch',
                ]);
            }
        }

        // 5. Sample Coupon
        $couponTable = $wpdb->prefix . 'appointpro_coupons';
        $wpdb->insert($couponTable, [
            'code' => 'WELCOME10',
            'discount_type' => 'percentage',
            'discount_value' => 10.00,
            'min_order_amount' => 30.00,
            'usage_limit_total' => 500,
            'used_count' => 0,
            'status' => 'active',
            'created_at' => $now,
        ]);
    }
}
