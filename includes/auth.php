<?php
/**
 * Authentication and Access Control
 * Islamiyya School Management System (ISMS)
 */

require_once __DIR__ . '/functions.php';

function is_logged_in(): bool {
    return !empty($_SESSION['user_id']) && !empty($_SESSION['role']);
}

function is_admin(): bool {
    return is_logged_in() && $_SESSION['role'] === 'admin';
}

function is_parent(): bool {
    return is_logged_in() && $_SESSION['role'] === 'parent';
}

function require_login(): void {
    if (!is_logged_in()) {
        set_flash('warning', 'Please log in to access this page.');
        header('Location: ' . BASE_URL . '/auth/login.php');
        exit;
    }
}

function require_admin(): void {
    require_login();
    if (!is_admin()) {
        set_flash('danger', 'Access denied. Administrator privileges required.');
        header('Location: ' . BASE_URL . '/parent/index.php');
        exit;
    }
}

function require_parent(): void {
    require_login();
    if (!is_parent()) {
        set_flash('danger', 'Access denied. Parent portal only.');
        header('Location: ' . BASE_URL . '/admin/index.php');
        exit;
    }
}

function get_current_parent_profile(): ?array {
    if (!is_parent()) return null;
    static $parent = null;
    if ($parent === null) {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("SELECT p.*, u.email, u.phone, u.username, u.status FROM parents p JOIN users u ON p.user_id = u.id WHERE u.id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $parent = $stmt->fetch();
        if (!$parent) {
            // If parents table row missing, create one automatically
            $stmt_u = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt_u->execute([$_SESSION['user_id']]);
            $u = $stmt_u->fetch();
            if ($u) {
                $ins = $pdo->prepare("INSERT INTO parents (user_id, full_name, residential_address) VALUES (?, ?, '')");
                $ins->execute([$u['id'], $u['username']]);
                $stmt->execute([$_SESSION['user_id']]);
                $parent = $stmt->fetch();
            }
        }
    }
    return $parent ?: null;
}
