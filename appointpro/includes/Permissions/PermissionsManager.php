<?php
declare(strict_types=1);

namespace AppointPro\Permissions;

/**
 * Manages WordPress roles and capability assignments for AppointPro.
 */
class PermissionsManager {
    public const CAP_MANAGE_SETTINGS = 'appointpro_manage_settings';
    public const CAP_MANAGE_SERVICES = 'appointpro_manage_services';
    public const CAP_MANAGE_STAFF = 'appointpro_manage_staff';
    public const CAP_MANAGE_CUSTOMERS = 'appointpro_manage_customers';
    public const CAP_MANAGE_APPOINTMENTS = 'appointpro_manage_appointments';
    public const CAP_VIEW_OWN_APPOINTMENTS = 'appointpro_view_own_appointments';
    public const CAP_EDIT_OWN_SCHEDULE = 'appointpro_edit_own_schedule';
    public const CAP_VIEW_REPORTS = 'appointpro_view_reports';

    public const ROLE_MANAGER = 'appointpro_manager';
    public const ROLE_STAFF = 'appointpro_staff';

    /**
     * Register custom roles and assign capabilities.
     *
     * @return void
     */
    public static function registerRolesAndCaps(): void {
        // Add capabilities to Administrator
        $admin = get_role('administrator');
        if ($admin) {
            foreach (self::getAllCapabilities() as $cap) {
                $admin->add_cap($cap);
            }
        }

        // Add or update Manager role
        add_role(self::ROLE_MANAGER, __('AppointPro Manager', 'appointpro'), [
            'read' => true,
            self::CAP_MANAGE_SETTINGS => true,
            self::CAP_MANAGE_SERVICES => true,
            self::CAP_MANAGE_STAFF => true,
            self::CAP_MANAGE_CUSTOMERS => true,
            self::CAP_MANAGE_APPOINTMENTS => true,
            self::CAP_VIEW_OWN_APPOINTMENTS => true,
            self::CAP_EDIT_OWN_SCHEDULE => true,
            self::CAP_VIEW_REPORTS => true,
        ]);

        // Add or update Staff role
        add_role(self::ROLE_STAFF, __('AppointPro Staff', 'appointpro'), [
            'read' => true,
            self::CAP_VIEW_OWN_APPOINTMENTS => true,
            self::CAP_EDIT_OWN_SCHEDULE => true,
            self::CAP_MANAGE_CUSTOMERS => false,
            self::CAP_MANAGE_SETTINGS => false,
            self::CAP_MANAGE_SERVICES => false,
            self::CAP_MANAGE_STAFF => false,
            self::CAP_MANAGE_APPOINTMENTS => false,
            self::CAP_VIEW_REPORTS => false,
        ]);
    }

    /**
     * Remove custom roles and capabilities on complete cleanup.
     *
     * @return void
     */
    public static function removeRolesAndCaps(): void {
        $admin = get_role('administrator');
        if ($admin) {
            foreach (self::getAllCapabilities() as $cap) {
                $admin->remove_cap($cap);
            }
        }

        remove_role(self::ROLE_MANAGER);
        remove_role(self::ROLE_STAFF);
    }

    /**
     * Get list of all plugin capabilities.
     *
     * @return array<string>
     */
    public static function getAllCapabilities(): array {
        return [
            self::CAP_MANAGE_SETTINGS,
            self::CAP_MANAGE_SERVICES,
            self::CAP_MANAGE_STAFF,
            self::CAP_MANAGE_CUSTOMERS,
            self::CAP_MANAGE_APPOINTMENTS,
            self::CAP_VIEW_OWN_APPOINTMENTS,
            self::CAP_EDIT_OWN_SCHEDULE,
            self::CAP_VIEW_REPORTS,
        ];
    }

    /**
     * Check if current user has capability.
     *
     * @param string $capability
     * @return bool
     */
    public static function check(string $capability): bool {
        return current_user_can($capability);
    }
}
