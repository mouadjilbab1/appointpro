<?php
declare(strict_types=1);

namespace AppointPro\Core;

use AppointPro\Permissions\PermissionsManager;

/**
 * AppointPro Main Plugin Orchestrator and Lifecycle Manager.
 */
class Plugin {
    private static ?Plugin $instance = null;
    private Container $container;

    /**
     * Singleton instance accessor.
     *
     * @return Plugin
     */
    public static function getInstance(): Plugin {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor.
     */
    private function __construct() {
        $this->container = new Container();
    }

    /**
     * Retrieve the IoC Container.
     *
     * @return Container
     */
    public function getContainer(): Container {
        return $this->container;
    }

    /**
     * Boot all plugin services, hooks, and integrations.
     *
     * @return void
     */
    public function boot(): void {
        $this->registerCronSchedules();
        $this->registerServiceBindings();

        // Lifecycle and Localization
        add_action('init', [$this, 'loadTextdomain']);
        add_action('init', [$this, 'registerShortcodes']);

        // Admin interface and menus
        if (is_admin()) {
            add_action('admin_menu', [$this, 'registerAdminMenus']);
            add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
        }

        // Frontend assets
        add_action('wp_enqueue_scripts', [$this, 'enqueueFrontendAssets']);

        // REST API
        add_action('rest_api_init', [$this, 'registerRestRoutes']);

        // Cron tasks
        add_action('appointpro_process_notification_queue', [$this, 'processNotificationQueue']);
        add_action('appointpro_cleanup_expired_locks', [$this, 'cleanupExpiredLocks']);

        // Gutenberg block registration
        add_action('init', [$this, 'registerGutenbergBlock']);
    }

    /**
     * Add custom WP-Cron intervals.
     *
     * @return void
     */
    protected function registerCronSchedules(): void {
        add_filter('cron_schedules', function ($schedules) {
            $schedules['five_minutes'] = [
                'interval' => 300,
                'display' => __('Every 5 Minutes', 'appointpro'),
            ];
            $schedules['fifteen_minutes'] = [
                'interval' => 900,
                'display' => __('Every 15 Minutes', 'appointpro'),
            ];
            return $schedules;
        });
    }

    /**
     * Register core services in the IoC Container.
     *
     * @return void
     */
    protected function registerServiceBindings(): void {
        // Register repositories and services
        $this->container->singleton('services.repository', \AppointPro\Services\ServiceRepository::class);
        $this->container->singleton('staff.repository', \AppointPro\Staff\StaffRepository::class);
        $this->container->singleton('customers.repository', \AppointPro\Customers\CustomerRepository::class);
        $this->container->singleton('locations.repository', \AppointPro\Locations\LocationRepository::class);
        $this->container->singleton('resources.repository', \AppointPro\Resources\ResourceRepository::class);
        $this->container->singleton('availability.engine', \AppointPro\Availability\AvailabilityEngine::class);
        $this->container->singleton('booking.engine', \AppointPro\Booking\BookingEngine::class);
        $this->container->singleton('payments.manager', \AppointPro\Payments\PaymentManager::class);
        $this->container->singleton('notifications.dispatcher', \AppointPro\Notifications\NotificationDispatcher::class);
        $this->container->singleton('reports.manager', \AppointPro\Reports\ReportsManager::class);
        $this->container->singleton('ai.assistant', \AppointPro\AI\AIAssistant::class);
        $this->container->singleton('webhooks.dispatcher', \AppointPro\Webhooks\WebhookDispatcher::class);
    }

    /**
     * Load plugin textdomain for translations.
     *
     * @return void
     */
    public function loadTextdomain(): void {
        load_plugin_textdomain(
            'appointpro',
            false,
            dirname(plugin_basename(APPOINTPRO_FILE)) . '/languages/'
        );
    }

    /**
     * Register Admin Menus matching Part 6 specifications.
     *
     * @return void
     */
    public function registerAdminMenus(): void {
        $capability = PermissionsManager::CAP_MANAGE_SETTINGS;

        add_menu_page(
            __('AppointPro', 'appointpro'),
            __('AppointPro', 'appointpro'),
            'read',
            'appointpro',
            [$this, 'renderAdminApp'],
            'dashicons-calendar-alt',
            25
        );

        $submenus = [
            'appointpro' => __('Dashboard', 'appointpro'),
            'appointpro-calendar' => __('Calendar', 'appointpro'),
            'appointpro-appointments' => __('Appointments', 'appointpro'),
            'appointpro-services' => __('Services', 'appointpro'),
            'appointpro-categories' => __('Categories', 'appointpro'),
            'appointpro-staff' => __('Staff', 'appointpro'),
            'appointpro-customers' => __('Customers', 'appointpro'),
            'appointpro-locations' => __('Locations', 'appointpro'),
            'appointpro-resources' => __('Resources', 'appointpro'),
            'appointpro-payments' => __('Payments', 'appointpro'),
            'appointpro-coupons' => __('Coupons', 'appointpro'),
            'appointpro-notifications' => __('Notifications', 'appointpro'),
            'appointpro-waiting-list' => __('Waiting List', 'appointpro'),
            'appointpro-reports' => __('Reports', 'appointpro'),
            'appointpro-integrations' => __('Integrations', 'appointpro'),
            'appointpro-ai' => __('AI Assistant', 'appointpro'),
            'appointpro-settings' => __('Settings', 'appointpro'),
        ];

        foreach ($submenus as $slug => $title) {
            add_submenu_page(
                'appointpro',
                $title . ' – ' . __('AppointPro', 'appointpro'),
                $title,
                'read',
                $slug,
                [$this, 'renderAdminApp']
            );
        }
    }

    /**
     * Render the single-page React Admin application container.
     *
     * @return void
     */
    public function renderAdminApp(): void {
        $page = sanitize_key($_GET['page'] ?? 'appointpro');
        echo '<div id="appointpro-admin-root" data-current-page="' . esc_attr($page) . '"></div>';
    }

    /**
     * Enqueue Admin Assets.
     *
     * @param string $hook
     * @return void
     */
    public function enqueueAdminAssets(string $hook): void {
        if (strpos($hook, 'appointpro') === false) {
            return;
        }

        wp_enqueue_style(
            'appointpro-admin-css',
            APPOINTPRO_URL . 'assets/css/admin.css',
            [],
            APPOINTPRO_VERSION
        );

        wp_enqueue_script(
            'appointpro-admin-bundle',
            APPOINTPRO_URL . 'assets/js/admin.bundle.js',
            ['wp-element', 'wp-i18n', 'wp-api-fetch'],
            APPOINTPRO_VERSION,
            true
        );

        $currentUser = wp_get_current_user();
        wp_localize_script('appointpro-admin-bundle', 'appointProConfig', [
            'apiUrl' => esc_url_raw(rest_url('appointpro/v1/')),
            'nonce' => wp_create_nonce('wp_rest'),
            'adminNonce' => wp_create_nonce(\AppointPro\Security\Security::NONCE_ACTION_ADMIN),
            'siteUrl' => site_url(),
            'currency' => get_option('appointpro_settings')['currency'] ?? 'USD',
            'currencySymbol' => get_option('appointpro_settings')['currency_symbol'] ?? '$',
            'dateFormat' => get_option('date_format', 'Y-m-d'),
            'timeFormat' => get_option('time_format', 'H:i'),
            'user' => [
                'id' => $currentUser->ID,
                'name' => $currentUser->display_name,
                'roles' => (array) $currentUser->roles,
                'caps' => [
                    'manageSettings' => current_user_can(PermissionsManager::CAP_MANAGE_SETTINGS),
                    'manageAppointments' => current_user_can(PermissionsManager::CAP_MANAGE_APPOINTMENTS),
                    'manageServices' => current_user_can(PermissionsManager::CAP_MANAGE_SERVICES),
                    'manageStaff' => current_user_can(PermissionsManager::CAP_MANAGE_STAFF),
                    'viewReports' => current_user_can(PermissionsManager::CAP_VIEW_REPORTS),
                ],
            ],
            'isRtl' => is_rtl(),
        ]);
    }

    /**
     * Enqueue Frontend Assets.
     *
     * @return void
     */
    public function enqueueFrontendAssets(): void {
        wp_register_style(
            'appointpro-frontend-css',
            APPOINTPRO_URL . 'assets/css/frontend.css',
            [],
            APPOINTPRO_VERSION
        );

        wp_register_script(
            'appointpro-frontend-bundle',
            APPOINTPRO_URL . 'assets/js/frontend.bundle.js',
            ['wp-element', 'wp-i18n'],
            APPOINTPRO_VERSION,
            true
        );

        wp_localize_script('appointpro-frontend-bundle', 'appointProFrontendConfig', [
            'apiUrl' => esc_url_raw(rest_url('appointpro/v1/')),
            'nonce' => wp_create_nonce('wp_rest'),
            'currency' => get_option('appointpro_settings')['currency'] ?? 'USD',
            'currencySymbol' => get_option('appointpro_settings')['currency_symbol'] ?? '$',
            'isRtl' => is_rtl(),
            'i18n' => [
                'selectService' => __('Select Service', 'appointpro'),
                'selectDate' => __('Select Date & Time', 'appointpro'),
                'customerInfo' => __('Your Information', 'appointpro'),
                'confirmBooking' => __('Confirm Booking', 'appointpro'),
            ]
        ]);
    }

    /**
     * Register REST API routes.
     *
     * @return void
     */
    public function registerRestRoutes(): void {
        $controllers = [
            \AppointPro\REST\DashboardController::class,
            \AppointPro\REST\ServicesController::class,
            \AppointPro\REST\StaffController::class,
            \AppointPro\REST\LocationsController::class,
            \AppointPro\REST\ResourcesController::class,
            \AppointPro\REST\AvailabilityController::class,
            \AppointPro\REST\AppointmentsController::class,
            \AppointPro\REST\CustomersController::class,
            \AppointPro\REST\PaymentsController::class,
            \AppointPro\REST\CouponsController::class,
            \AppointPro\REST\NotificationsController::class,
            \AppointPro\REST\WaitingListController::class,
            \AppointPro\REST\ReportsController::class,
            \AppointPro\REST\IntegrationsController::class,
            \AppointPro\REST\AIController::class,
            \AppointPro\REST\SettingsController::class,
            \AppointPro\REST\WebhooksController::class,
        ];

        foreach ($controllers as $controllerClass) {
            if (class_exists($controllerClass)) {
                $controller = new $controllerClass();
                if (method_exists($controller, 'registerRoutes')) {
                    $controller->registerRoutes();
                }
            }
        }
    }

    /**
     * Register Shortcodes.
     *
     * @return void
     */
    public function registerShortcodes(): void {
        add_shortcode('appointpro_booking', [\AppointPro\Booking\BookingWizardShortcode::class, 'render']);
        add_shortcode('appointpro_customer_portal', [\AppointPro\Portals\CustomerPortal::class, 'render']);
        add_shortcode('appointpro_staff_portal', [\AppointPro\Portals\StaffPortal::class, 'render']);
    }

    /**
     * Register Gutenberg Block.
     *
     * @return void
     */
    public function registerGutenbergBlock(): void {
        if (!function_exists('register_block_type')) {
            return;
        }

        register_block_type('appointpro/booking-wizard', [
            'editor_script' => 'appointpro-admin-bundle',
            'render_callback' => [\AppointPro\Booking\BookingWizardShortcode::class, 'render'],
        ]);
    }

    /**
     * Process asynchronous notification queue.
     *
     * @return void
     */
    public function processNotificationQueue(): void {
        if ($this->container->has('notifications.dispatcher')) {
            $dispatcher = $this->container->get('notifications.dispatcher');
            $dispatcher->processQueue();
        }
    }

    /**
     * Cleanup expired temporary slot locks.
     *
     * @return void
     */
    public function cleanupExpiredLocks(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'appointpro_slot_locks';
        $now = gmdate('Y-m-d H:i:s');
        $wpdb->query($wpdb->prepare("DELETE FROM `{$table}` WHERE expires_at < %s", $now));
    }
}
