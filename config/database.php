<?php
/**
 * Database Configuration & Connection
 * Islamiyya School Management System (ISMS)
 */

if (!defined('DB_HOST')) define('DB_HOST', '127.0.0.1');
if (!defined('DB_PORT')) define('DB_PORT', '3306');
if (!defined('DB_NAME')) define('DB_NAME', 'islamiyya_db');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');

function get_db_connection(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Friendly error message for users
            die("<h3>Database Connection Error</h3><p>Could not connect to the Islamiyya School database. Please ensure MySQL is running and the database has been imported.</p><p><a href='/islamiyya/install.php'>Click here to run the Web Installer</a></p><hr><small>Technical Error: " . htmlspecialchars($e->getMessage()) . "</small>");
        }
    }
    return $pdo;
}
