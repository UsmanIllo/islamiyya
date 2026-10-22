<?php
/**
 * Global Helper Functions & Utilities
 * Islamiyya School Management System (ISMS)
 */

require_once __DIR__ . '/../config/app.php';

// Safe HTML Output
function e($str): string {
    return htmlspecialchars((string)($str ?? ''), ENT_QUOTES, 'UTF-8');
}

// Format Currency in Nigerian Naira
function format_currency($amount): string {
    $symbol = get_setting('currency_symbol', '₦');
    return $symbol . number_format((float)$amount, 2);
}

// Format Date
function format_date($date, string $format = 'd M Y'): string {
    if (!$date || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') return 'N/A';
    return date($format, strtotime($date));
}

// CSRF Protection
function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . e($token) . '">';
}

function verify_csrf_token(?string $token): bool {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// Flash Notifications
function set_flash(string $type, string $message): void {
    if (!isset($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function display_flash(): string {
    if (empty($_SESSION['flash'])) return '';
    $output = '';
    foreach ($_SESSION['flash'] as $flash) {
        $alert_type = match ($flash['type']) {
            'success' => 'alert-success',
            'error', 'danger' => 'alert-danger',
            'warning' => 'alert-warning',
            default => 'alert-info'
        };
        $icon = match ($flash['type']) {
            'success' => '<i class="bi bi-check-circle-fill me-2"></i>',
            'error', 'danger' => '<i class="bi bi-exclamation-triangle-fill me-2"></i>',
            'warning' => '<i class="bi bi-exclamation-circle-fill me-2"></i>',
            default => '<i class="bi bi-info-circle-fill me-2"></i>'
        };
        $output .= '<div class="alert ' . $alert_type . ' alert-dismissible fade show d-flex align-items-center shadow-sm" role="alert">';
        $output .= $icon . '<div>' . e($flash['message']) . '</div>';
        $output .= '<button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>';
        $output .= '</div>';
    }
    unset($_SESSION['flash']);
    return $output;
}

// Audit Logger
function log_audit(string $action, string $entity_type, ?int $entity_id, string $description): void {
    try {
        $pdo = get_db_connection();
        $user_id = $_SESSION['user_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, action, entity_type, entity_id, description, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$user_id, $action, $entity_type, $entity_id, $description, $ip]);
    } catch (Exception $e) {
        // Silently log or ignore to not break main flow
        error_log("Audit log failed: " . $e->getMessage());
    }
}

// Unique Reference Generators
function generate_student_id(): string {
    $pdo = get_db_connection();
    $year = date('Y');
    $stmt = $pdo->query("SELECT MAX(id) as max_id FROM students");
    $row = $stmt->fetch();
    $next_id = ($row['max_id'] ?? 0) + 1;
    return sprintf("ISMS/%s/%03d", $year, $next_id);
}

function generate_receipt_number(): string {
    $pdo = get_db_connection();
    $year = date('Y');
    $stmt = $pdo->query("SELECT MAX(id) as max_id FROM receipts");
    $row = $stmt->fetch();
    $next_id = ($row['max_id'] ?? 0) + 1;
    return sprintf("REC-%s-%04d", $year, $next_id);
}

function generate_invoice_number(): string {
    $pdo = get_db_connection();
    $year = date('Y');
    $stmt = $pdo->query("SELECT MAX(id) as max_id FROM student_invoices");
    $row = $stmt->fetch();
    $next_id = ($row['max_id'] ?? 0) + 1;
    return sprintf("INV-%s-%04d", $year, $next_id);
}

function generate_payment_reference(): string {
    return 'PAY-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
}

// Recalculate Student Invoice balance & status
function recalculate_invoice(int $invoice_id): void {
    $pdo = get_db_connection();
    // Sum all successful payments for this invoice
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as total_paid FROM payments WHERE invoice_id = ? AND payment_status = 'successful'");
    $stmt->execute([$invoice_id]);
    $paid = (float)$stmt->fetchColumn();

    $stmt2 = $pdo->prepare("SELECT total_amount, due_date FROM student_invoices WHERE id = ?");
    $stmt2->execute([$invoice_id]);
    $inv = $stmt2->fetch();
    if (!$inv) return;

    $total = (float)$inv['total_amount'];
    $balance = max(0, $total - $paid);

    $status = 'unpaid';
    if ($paid >= $total && $total > 0) {
        $status = 'paid';
    } elseif ($paid > 0 && $paid < $total) {
        $status = 'partially_paid';
    } elseif ($inv['due_date'] && strtotime($inv['due_date']) < time() && $balance > 0) {
        $status = 'overdue';
    }

    $update = $pdo->prepare("UPDATE student_invoices SET amount_paid = ?, balance = ?, status = ? WHERE id = ?");
    $update->execute([$paid, $balance, $status, $invoice_id]);
}

// Counts for badges
function get_unread_notification_count(int $user_id): int {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notification_recipients WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

function get_pending_payment_count(): int {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->query("SELECT COUNT(*) FROM payments WHERE payment_status = 'pending'");
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

function get_pending_registration_count(): int {
    try {
        $pdo = get_db_connection();
        $stmt = $pdo->query("SELECT COUNT(*) FROM students WHERE registration_status = 'pending'");
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

// Current Academic Session & Term helpers
function get_current_session_term(): array {
    $pdo = get_db_connection();
    $stmt = $pdo->query("
        SELECT s.id as session_id, s.session_name, t.id as term_id, t.term_name, t.start_date, t.end_date
        FROM academic_sessions s
        LEFT JOIN academic_terms t ON t.session_id = s.id AND t.is_current = 1
        WHERE s.is_current = 1
        LIMIT 1
    ");
    $data = $stmt->fetch();
    return $data ?: [
        'session_id' => 1,
        'session_name' => '2025/2026',
        'term_id' => 3,
        'term_name' => 'Third Term',
        'start_date' => null,
        'end_date' => null
    ];
}

// Send Notification to Users
function send_broadcast_notification(int $sender_id, string $title, string $message, string $target_type = 'all', ?int $target_parent_id = null, ?int $target_class_id = null): int {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("INSERT INTO notifications (sender_id, title, message, target_type, target_parent_id, target_class_id) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$sender_id, $title, $message, $target_type, $target_parent_id, $target_class_id]);
    $notification_id = (int)$pdo->lastInsertId();

    // Determine recipients
    $recipient_user_ids = [];
    if ($target_type === 'all') {
        $res = $pdo->query("SELECT id FROM users WHERE role = 'parent' AND status = 'active'");
        $recipient_user_ids = $res->fetchAll(PDO::FETCH_COLUMN);
    } elseif ($target_type === 'selected_parent' && $target_parent_id) {
        $res = $pdo->prepare("SELECT user_id FROM parents WHERE id = ?");
        $res->execute([$target_parent_id]);
        $uid = $res->fetchColumn();
        if ($uid) $recipient_user_ids[] = $uid;
    } elseif ($target_type === 'class' && $target_class_id) {
        $res = $pdo->prepare("
            SELECT DISTINCT p.user_id 
            FROM students s 
            JOIN parents p ON s.parent_id = p.id 
            WHERE s.current_class_id = ? AND s.enrolment_status = 'active'
        ");
        $res->execute([$target_class_id]);
        $recipient_user_ids = $res->fetchAll(PDO::FETCH_COLUMN);
    }

    if (!empty($recipient_user_ids)) {
        $recip_stmt = $pdo->prepare("INSERT INTO notification_recipients (notification_id, user_id, is_read) VALUES (?, ?, 0)");
        foreach ($recipient_user_ids as $uid) {
            $recip_stmt->execute([$notification_id, $uid]);
        }
    }

    return $notification_id;
}
