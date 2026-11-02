<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Define WordPress Constants if undefined
if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/../');
}
if (!defined('APPOINTPRO_VERSION')) {
    define('APPOINTPRO_VERSION', '1.0.0');
}
if (!defined('APPOINTPRO_DB_VERSION')) {
    define('APPOINTPRO_DB_VERSION', '1.0.0');
}
if (!defined('APPOINTPRO_FILE')) {
    define('APPOINTPRO_FILE', __DIR__ . '/../appointpro.php');
}

// Global Options & Transients Store for Mock WP
$GLOBALS['wp_mock_options'] = [];
$GLOBALS['wp_mock_transients'] = [];
$GLOBALS['wp_mock_current_user_id'] = 1;
$GLOBALS['wp_mock_caps'] = ['manage_options' => true, 'read' => true];

if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}
if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}

// Mock WordPress functions
if (!function_exists('__')) {
    function __(string $text, string $domain = 'default'): string {
        return $text;
    }
}
if (!function_exists('_e')) {
    function _e(string $text, string $domain = 'default'): void {
        echo $text;
    }
}
if (!function_exists('_n')) {
    function _n(string $single, string $plural, int $number, string $domain = 'default'): string {
        return $number === 1 ? $single : $plural;
    }
}
if (!function_exists('esc_html')) {
    function esc_html(string $text): string {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('esc_attr')) {
    function esc_attr(string $text): string {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('esc_url_raw')) {
    function esc_url_raw(string $url): string {
        return filter_var($url, FILTER_SANITIZE_URL) ?: '';
    }
}
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $str): string {
        $filtered = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', $str);
        return strip_tags(trim($filtered));
    }
}
if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field(string $str): string {
        return strip_tags(trim($str));
    }
}
if (!function_exists('sanitize_key')) {
    function sanitize_key(string $key): string {
        return strtolower(preg_replace('/[^a-z0-9_\-]/', '', $key));
    }
}
if (!function_exists('sanitize_email')) {
    function sanitize_email(string $email): string {
        return filter_var(trim($email), FILTER_SANITIZE_EMAIL) ?: '';
    }
}
if (!function_exists('is_email')) {
    function is_email(string $email): bool {
        return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
    }
}
if (!function_exists('sanitize_hex_color')) {
    function sanitize_hex_color(?string $color): ?string {
        if (!$color) return null;
        return preg_match('|^#([A-Fa-f0-9]{3}){1,2}$|', $color) ? $color : null;
    }
}
if (!function_exists('wp_kses_post')) {
    function wp_kses_post(string $data): string {
        return strip_tags($data, '<p><a><strong><em><br>');
    }
}
if (!function_exists('wp_timezone_string')) {
    function wp_timezone_string(): string {
        return 'UTC';
    }
}
if (!function_exists('get_bloginfo')) {
    function get_bloginfo(string $show = 'name'): string {
        return 'AppointPro Test Business';
    }
}
if (!function_exists('get_option')) {
    function get_option(string $option, mixed $default = false): mixed {
        return $GLOBALS['wp_mock_options'][$option] ?? $default;
    }
}
if (!function_exists('update_option')) {
    function update_option(string $option, mixed $value): bool {
        $GLOBALS['wp_mock_options'][$option] = $value;
        return true;
    }
}
if (!function_exists('delete_option')) {
    function delete_option(string $option): bool {
        unset($GLOBALS['wp_mock_options'][$option]);
        return true;
    }
}
if (!function_exists('get_transient')) {
    function get_transient(string $transient): mixed {
        return $GLOBALS['wp_mock_transients'][$transient] ?? false;
    }
}
if (!function_exists('set_transient')) {
    function set_transient(string $transient, mixed $value, int $expiration = 0): bool {
        $GLOBALS['wp_mock_transients'][$transient] = $value;
        return true;
    }
}
if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce(string|int $action = -1): string {
        return substr(md5((string)$action . 'mock_nonce_secret'), 0, 10);
    }
}
if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce(?string $nonce, string|int $action = -1): bool {
        if (empty($nonce)) return false;
        return $nonce === wp_create_nonce($action);
    }
}
if (!function_exists('current_user_can')) {
    function current_user_can(string $capability): bool {
        return !empty($GLOBALS['wp_mock_caps'][$capability]) || !empty($GLOBALS['wp_mock_caps']['manage_options']);
    }
}
if (!function_exists('get_current_user_id')) {
    function get_current_user_id(): int {
        return (int) ($GLOBALS['wp_mock_current_user_id'] ?? 1);
    }
}
if (!function_exists('wp_generate_password')) {
    function wp_generate_password(int $length = 12, bool $special_chars = true): string {
        return substr(bin2hex(random_bytes($length)), 0, $length);
    }
}
if (!function_exists('wp_mail')) {
    function wp_mail(string|array $to, string $subject, string $message, string|array $headers = ''): bool {
        $GLOBALS['wp_mock_sent_emails'][] = [
            'to' => $to,
            'subject' => $subject,
            'message' => $message,
        ];
        return true;
    }
}
if (!function_exists('site_url')) {
    function site_url(string $path = ''): string {
        return 'https://example.com' . ($path ? '/' . ltrim($path, '/') : '');
    }
}
if (!function_exists('dbDelta')) {
    function dbDelta(string|array $queries, bool $execute = true): array {
        return [];
    }
}

// Mock WordPress Database Class ($wpdb)
class MockWPDB {
    public string $prefix = 'wp_';
    public int $insert_id = 1;
    public array $tables = [];
    public array $queries = [];

    public function get_charset_collate(): string {
        return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
    }

    public function prepare(string $query, ...$args): string {
        if (isset($args[0]) && is_array($args[0])) {
            $args = $args[0];
        }
        foreach ($args as $arg) {
            $val = is_numeric($arg) ? $arg : "'" . addslashes((string) $arg) . "'";
            $query = preg_replace('/%[sdfF]/', (string) $val, $query, 1);
        }
        return $query;
    }

    public function query(string $query): int|bool {
        $this->queries[] = $query;
        return 1;
    }

    public function get_var(string $query): mixed {
        $this->queries[] = $query;
        if (stripos($query, 'COUNT(*)') !== false) {
            return 0; // default 0 conflicts
        }
        return null;
    }

    public function get_row(string $query, string $output = 'OBJECT'): mixed {
        $this->queries[] = $query;
        foreach ($this->tables as $tableName => $rows) {
            if (stripos($query, $tableName) !== false) {
                if (preg_match('/id\s*=\s*(\d+)/i', $query, $matches)) {
                    $searchId = (int) $matches[1];
                    foreach ($rows as $row) {
                        if (($row['id'] ?? 0) === $searchId) {
                            return $row;
                        }
                    }
                }
                if (!empty($rows)) {
                    return end($rows);
                }
            }
        }
        // If querying appointments table specifically and not empty in test
        if (stripos($query, 'appointpro_appointments') !== false && !empty($this->tables['wp_appointpro_appointments'])) {
            return end($this->tables['wp_appointpro_appointments']);
        }
        return null;
    }

    public function get_results(string $query, string $output = 'OBJECT'): array {
        $this->queries[] = $query;
        return [];
    }

    public function get_col(string $query): array {
        $this->queries[] = $query;
        return [];
    }

    public function insert(string $table, array $data, array $format = []): int {
        $this->insert_id++;
        $this->tables[$table][] = array_merge(['id' => $this->insert_id], $data);
        return 1;
    }

    public function update(string $table, array $data, array $where, array $format = [], array $where_format = []): int {
        return 1;
    }

    public function delete(string $table, array $where, array $where_format = []): int {
        return 1;
    }

    public function esc_like(string $text): string {
        return addcslashes($text, '_%\\');
    }
}

global $wpdb;
$wpdb = new MockWPDB();
