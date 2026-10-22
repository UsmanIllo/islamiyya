<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

if (!empty($_SESSION['user_id'])) {
    log_audit('USER_LOGOUT', 'users', (int)$_SESSION['user_id'], "User {$_SESSION['username']} logged out.");
}

// Clear all session data
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();

// Redirect to login with message
session_start();
set_flash('info', 'You have been logged out securely. Barakallahu feekum.');
header('Location: ' . BASE_URL . '/auth/login.php');
exit;
