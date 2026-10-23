<?php
/**
 * Database Configuration & Connection
 * Islamiyya School Management System (ISMS)
 */

$host = $_ENV['DB_HOST'] ?? getenv('DB_HOST') ?: 'sql.freedb.tech';
$port = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: '3306';
$dbname = $_ENV['DB_NAME'] ?? getenv('DB_NAME') ?: 'freedb_T10ZP7cs';
$user = $_ENV['DB_USER'] ?? getenv('DB_USER') ?: 'u_aZODTo';
$pass = $_ENV['DB_PASS'] ?? getenv('DB_PASS') ?: '';

try {
    $dsn = "mysql :host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,]
    ]);
} catch (PDOException $e) {
    die("Database Connection Error: " . $e->getMessage());
}
