<?php
/**
 * Application Core Configuration
 * Islamiyya School Management System (ISMS)
 */

// Start output buffering globally to prevent "headers already sent" issues
if (!ob_get_level()) {
    ob_start();
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Security session settings
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

require_once __DIR__ . '/database.php';

// Base Path & URLs
define('APP_NAME', 'Islamiyya School Management System');
define('APP_SHORT_NAME', 'ISMS');

// Auto-detect base URL (handles Wamp root / subfolder / virtualhost)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));

// Detect root directory for /islamiyya
if (strpos($script_dir, '/admin') !== false) {
    $base_dir = substr($script_dir, 0, strpos($script_dir, '/admin'));
} elseif (strpos($script_dir, '/parent') !== false) {
    $base_dir = substr($script_dir, 0, strpos($script_dir, '/parent'));
} elseif (strpos($script_dir, '/auth') !== false) {
    $base_dir = substr($script_dir, 0, strpos($script_dir, '/auth'));
} else {
    $base_dir = rtrim($script_dir, '/');
}
define('BASE_URL', rtrim($protocol . $host . $base_dir, '/'));

// Upload paths
define('UPLOADS_PATH', dirname(__DIR__) . '/assets/uploads');
define('UPLOADS_URL', BASE_URL . '/assets/uploads');

// Load dynamic school settings from DB
function get_school_settings(): array {
    static $settings = null;
    if ($settings === null) {
        try {
            $pdo = get_db_connection();
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM school_settings");
            $rows = $stmt->fetchAll();
            $settings = [];
            foreach ($rows as $row) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            $settings = [
                'school_name' => 'Darul Huda Islamic Academy',
                'school_motto' => 'Excellence in Quranic & Islamic Education',
                'school_phone' => '+234 803 123 4567',
                'school_email' => 'info@darulhuda.edu.ng',
                'school_address' => 'Plot 14, Al-Hikmah Way, Abuja, Nigeria',
                'currency_symbol' => '₦',
                'currency_code' => 'NGN',
                'bank_name' => 'Jaiz Bank PLC',
                'bank_account_name' => 'Darul Huda Islamic Academy',
                'bank_account_number' => '0012345678',
            ];
        }
    }
    return $settings;
}

function get_setting(string $key, string $default = ''): string {
    $settings = get_school_settings();
    return $settings[$key] ?? $default;
}
