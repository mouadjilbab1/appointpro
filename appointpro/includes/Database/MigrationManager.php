<?php
declare(strict_types=1);

namespace AppointPro\Database;

/**
 * Manages database table migrations and upgrades across plugin versions.
 */
class MigrationManager {
    /**
     * Run all migrations and ensure tables are up-to-date.
     *
     * @return bool
     */
    public function migrate(): bool {
        global $wpdb;

        if (!function_exists('dbDelta')) {
            $upgradeFile = ABSPATH . 'wp-admin/includes/upgrade.php';
            if (file_exists($upgradeFile)) {
                require_once $upgradeFile;
            }
        }

        $collate = $wpdb->get_charset_collate();
        $prefix = $wpdb->prefix;
        $tables = Schema::getTableDefinitions($prefix, $collate);

        foreach ($tables as $sql) {
            dbDelta($sql);
        }

        $this->seedInitialData();

        update_option('appointpro_db_version', APPOINTPRO_DB_VERSION);

        return true;
    }

    /**
     * Seed initial default settings and records on first install.
     *
     * @return void
     */
    protected function seedInitialData(): void {
        global $wpdb;

        // Default plugin settings
        if (!get_option('appointpro_settings')) {
            $defaultSettings = [
                'business_name' => get_bloginfo('name') ?: 'My Business',
                'business_email' => get_bloginfo('admin_email'),
                'business_phone' => '',
                'timezone' => wp_timezone_string(),
                'currency' => 'USD',
                'currency_symbol' => '$',
                'currency_position' => 'before', // before or after
                'date_format' => get_option('date_format', 'Y-m-d'),
                'time_format' => get_option('time_format', 'H:i'),
                'default_slot_step' => 30, // 30 minutes
                'allow_guest_booking' => 1,
                'auto_confirm_appointments' => 1,
                'primary_color' => '#4f46e5',
                'secondary_color' => '#06b6d4',
                'delete_data_on_uninstall' => 0,
            ];
            update_option('appointpro_settings', $defaultSettings);
        }

        // Check if category exists; if empty, insert default 'General' category
        $categoryTable = $wpdb->prefix . 'appointpro_categories';
        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$categoryTable}`");
        if ($count === 0) {
            $now = gmdate('Y-m-d H:i:s');
            $wpdb->insert($categoryTable, [
                'name' => 'General Services',
                'description' => 'Default service category',
                'color' => '#4f46e5',
                'position' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
