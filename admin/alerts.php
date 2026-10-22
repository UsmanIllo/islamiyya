<?php
$page_title = "Alerts & Reminders";
$active_tab = "alerts";
require_once __DIR__ . '/../includes/admin_header.php';

$pdo = get_db_connection();
$cur_term = get_current_session_term();

// 1-Click Send Fee Reminders to All Debtors POST
if (isset($_POST['send_bulk_reminders']) && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $reminder_msg = trim($_POST['reminder_message'] ?? 'Dear Parent, please be reminded that your child has an outstanding school fee balance for the current academic term. Kindly visit the parent portal to settle the balance.');
    
    // Find all parents of students with balance > 0
    $stmt_d = $pdo->query("
        SELECT DISTINCT p.id as parent_id, p.full_name, u.id as user_id
        FROM student_invoices inv
        JOIN students s ON inv.student_id = s.id
        JOIN parents p ON s.parent_id = p.id
        JOIN users u ON p.user_id = u.id
        WHERE inv.balance > 0
    ");
    $debtor_parents = $stmt_d->fetchAll();

    $sent = 0;
    foreach ($debtor_parents as $dp) {
        send_broadcast_notification(
            (int)$_SESSION['user_id'],
            "Outstanding Fee Payment Reminder",
            $reminder_msg,
            "selected_parent",
            (int)$dp['parent_id']
        );
        $sent++;
    }

    log_audit('FEE_REMINDERS_SENT', 'notifications', null, "Dispatched fee reminders to {$sent} parent(s)");
    set_flash('success', "Fee reminder alerts sent successfully to {$sent} parent(s).");
    header('Location: ' . BASE_URL . '/admin/alerts.php');
    exit;
}

// Fetch Unpaid / Overdue Invoices
$stmt_invoices = $pdo->query("
    SELECT inv.*, s.first_name, s.last_name, s.student_id_number, c.class_name,
           pt.id as parent_id, pt.full_name as parent_name, u.phone as parent_phone
    FROM student_invoices inv
    JOIN students s ON inv.student_id = s.id
    LEFT JOIN classes c ON s.current_class_id = c.id
    JOIN parents pt ON s.parent_id = pt.id
    JOIN users u ON pt.user_id = u.id
    WHERE inv.balance > 0
    ORDER BY inv.balance DESC
");
$debtors = $stmt_invoices->fetchAll();

// Pending Registrations & Pending Payments
$pending_regs = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE registration_status = 'pending'")->fetchColumn();
$pending_pays = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE payment_status = 'pending'")->fetchColumn();
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold text-islamic mb-1">
            <i class="bi bi-bell-fill text-gold me-2"></i> System Alerts & Fee Debtors
        </h3>
        <p class="text-muted small mb-0">Monitor overdue accounts, unpaid balances, and dispatch automated payment reminders.</p>
    </div>
    <button type="button" class="btn btn-danger btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#bulkReminderModal">
        <i class="bi bi-megaphone-fill me-1"></i> Send Reminders to All Debtors (<?= count($debtors) ?>)
    </button>
</div>

<!-- Quick Alert Badges -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card stat-card danger-border p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-muted small fw-bold">Unpaid / Debtor Invoices</span>
                    <h3 class="fw-bold text-danger mt-1 mb-0"><?= count($debtors) ?></h3>
                </div>
                <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                    <i class="bi bi-exclamation-octagon-fill"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card gold-border p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-muted small fw-bold">Pending Registrations</span>
                    <h3 class="fw-bold text-warning mt-1 mb-0"><?= $pending_regs ?></h3>
                </div>
                <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                    <i class="bi bi-person-plus-fill"></i>
                </div>
            </div>
            <div class="mt-2 small"><a href="<?= BASE_URL ?>/admin/registrations.php" class="text-decoration-none">Review now <i class="bi bi-arrow-right"></i></a></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card p-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-muted small fw-bold">Pending Payment Verifications</span>
                    <h3 class="fw-bold text-islamic mt-1 mb-0"><?= $pending_pays ?></h3>
                </div>
                <div class="stat-icon bg-islamic-soft text-islamic">
                    <i class="bi bi-cash-coin"></i>
                </div>
            </div>
            <div class="mt-2 small"><a href="<?= BASE_URL ?>/admin/verify_payments.php" class="text-decoration-none">Verify claims <i class="bi bi-arrow-right"></i></a></div>
        </div>
    </div>
</div>

<!-- Debtors Table -->
<div class="card card-modern">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h5 class="mb-0 text-danger fw-bold">
            <i class="bi bi-wallet2 me-2"></i> Outstanding Fee Accounts
        </h5>
        <input type="text" class="form-control form-control-sm table-search-input" data-target-table="debtors_table" placeholder="Filter debtors..." style="width: 200px;">
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="debtors_table">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Student Name (ID)</th>
                        <th>Class</th>
                        <th>Parent Name</th>
                        <th>Parent Phone</th>
                        <th>Total Fee</th>
                        <th>Amount Paid</th>
                        <th>Outstanding Debt (₦)</th>
                        <th>Due Date</th>
                        <th class="text-end pe-4">Direct Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($debtors)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-success fw-bold">
                                <i class="bi bi-check-circle-fill fs-1 d-block mb-2"></i>
                                No fee debtors! All school fees have been paid.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($debtors as $d): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-islamic"><?= e($d['first_name'] . ' ' . $d['last_name']) ?></div>
                                    <small class="text-muted"><?= e($d['student_id_number'] ?? 'Pending') ?></small>
                                </td>
                                <td><?= e($d['class_name'] ?? 'Class') ?></td>
                                <td><?= e($d['parent_name']) ?></td>
                                <td>
                                    <a href="tel:<?= e($d['parent_phone']) ?>" class="text-decoration-none"><?= e($d['parent_phone']) ?></a>
                                </td>
                                <td><?= format_currency($d['total_amount']) ?></td>
                                <td class="text-success"><?= format_currency($d['amount_paid']) ?></td>
                                <td class="fw-bold text-danger fs-6"><?= format_currency($d['balance']) ?></td>
                                <td class="small text-muted"><?= format_date($d['due_date']) ?></td>
                                <td class="text-end pe-4">
                                    <a href="<?= BASE_URL ?>/admin/notifications.php?target_parent_id=<?= $d['parent_id'] ?>" class="btn btn-sm btn-outline-danger" title="Send individual reminder">
                                        <i class="bi bi-chat-left-dots me-1"></i> Remind
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Bulk Reminders -->
<div class="modal fade" id="bulkReminderModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content text-start">
            <form method="POST" action="">
                <?= csrf_field() ?>
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-megaphone-fill me-2"></i> Send Fee Reminders to All Debtors
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="small text-muted mb-3">
                        This action will dispatch an immediate in-app notification reminder to the parent accounts of all <strong><?= count($debtors) ?></strong> students with outstanding balances.
                    </p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Reminder Message</label>
                        <textarea name="reminder_message" class="form-control" rows="4" required>Assalamu Alaikum, esteemed parent. This is a gentle reminder from <?= e(get_setting('school_name')) ?> regarding the outstanding school fees for your child. Please log into the portal to complete your payment before the upcoming deadline.</textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="send_bulk_reminders" value="1" class="btn btn-danger btn-sm px-4">
                        Dispatch Reminders
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
