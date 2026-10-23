<?php
/**
 * Database Configuration & Connection
 * Islamiyya School Management System (ISMS)
 */

$host =  getenv('DB_HOST');
if (!$host) { $host = 'sql.freedb.tech';
    # code...
}
$port = getenv('DB_PORT');
if (!$port) { $port = '3306';
    # code...
}
$dbname = getenv('DB_NAME');
if (!$dbname) { $dbname = 'freedb_T10ZP7cs';
    # code...
}
$user = getenv('DB_USER');
if (!$user) { $user = 'u_aZODTo';
    # code...
}
$pass = getenv('DB_PASS');
if (!$pass) { $pass = '';
    # code...
}

try {
    $dsn = "mysql :host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die("Database Connection Error: " . $e->getMessage());
}
