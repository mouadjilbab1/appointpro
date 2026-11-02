<?php
/**
 * Plugin Name: AppointPro – Ultimate WordPress Appointment Booking & Scheduling System
 * Plugin URI: https://appointpro.io
 * Description: High-performance, enterprise-grade appointment booking, scheduling, staff management, multi-channel notifications, and multi-gateway payment processing system for WordPress.
 * Version: 1.0.0
 * Author: AppointPro Team
 * Author URI: https://appointpro.io
 * Text Domain: appointpro
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Define Core Plugin Constants
define('APPOINTPRO_VERSION', '1.0.0');
define('APPOINTPRO_FILE', __FILE__);
define('APPOINTPRO_PATH', plugin_dir_path(__FILE__));
define('APPOINTPRO_URL', plugin_dir_url(__FILE__));
define('APPOINTPRO_BASENAME', plugin_basename(__FILE__));
define('APPOINTPRO_DB_VERSION', '1.0.0');

if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}
if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}

// Require Composer Autoloader
if (file_exists(APPOINTPRO_PATH . 'vendor/autoload.php')) {
    require_once APPOINTPRO_PATH . 'vendor/autoload.php';
} else {
    spl_autoload_register(function ($class) {
        $prefix = 'AppointPro\\';
        $base_dir = APPOINTPRO_PATH . 'includes/';
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            return;
        }
        $relative_class = substr($class, $len);
        $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
        if (file_exists($file)) {
            require $file;
        }
    });
}

/**
 * Register Activation, Deactivation and Uninstall Hooks
 */
register_activation_hook(APPOINTPRO_FILE, ['AppointPro\\Activator\\Activator', 'activate']);
register_deactivation_hook(APPOINTPRO_FILE, ['AppointPro\\Deactivator\\Deactivator', 'deactivate']);

/**
 * Main Plugin Instance Function
 *
 * @return \AppointPro\Core\Plugin
 */
function appointpro(): \AppointPro\Core\Plugin {
    return \AppointPro\Core\Plugin::getInstance();
}

// Bootstrap Plugin
add_action('plugins_loaded', function () {
    appointpro()->boot();
});
