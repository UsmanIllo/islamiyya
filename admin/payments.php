<?php
$page_title = "Transactions & Payments";
$active_tab = "payments";
require_once __DIR__ . '/../includes/admin_header.php';

$pdo = get_db_connection();

// CSV Export Request
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=islamiyya_transactions_' . date('Y-m-d') . '.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Payment Reference', 'Transaction Reference', 'Receipt Number', 'Student Name', 'Student ID', 'Parent Name', 'Parent Phone', 'Amount (NGN)', 'Method', 'Channel', 'Status', 'Date']);

    $stmt_exp = $pdo->query("
        SELECT p.*, r.receipt_number, s.first_name, s.last_name, s.student_id_number, pt.full_name as parent_name, u.phone as parent_phone
        FROM payments p
        JOIN students s ON p.student_id = s.id
        JOIN parents pt ON p.parent_id = pt.id
        JOIN users u ON pt.user_id = u.id
        LEFT JOIN receipts r ON p.id = r.payment_id
        ORDER BY p.id DESC
    ");
    while ($row = $stmt_exp->fetch()) {
        fputcsv($out, [
            $row['payment_reference'],
            $row['transaction_reference'],
            $row['receipt_number'] ?? 'N/A',
            $row['first_name'] . ' ' . $row['last_name'],
            $row['student_id_number'] ?? 'N/A',
            $row['parent_name'],
            $row['parent_phone'],
            $row['amount'],
            $row['payment_method'],
            $row['payment_channel'],
            $row['payment_status'],
            $row['created_at']
        ]);
    }
    fclose($out);
    exit;
}

// Manual Payment Recording by Admin (Cash / POS at Bursary Office) POST
if (isset($_POST['record_manual_payment']) && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $invoice_id = (int)$_POST['invoice_id'];
    $amount = (float)$_POST['amount'];
    $payment_method = $_POST['payment_method']; // 'cash', 'pos', 'bank_transfer'
    $transaction_reference = trim($_POST['transaction_reference'] ?? '');
    $notes = trim($_POST['notes'] ?? 'Direct payment recorded at school bursary office.');

    // Fetch invoice details
    $stmt_inv = $pdo->prepare("
        SELECT inv.*, s.first_name, s.last_name, s.student_id_number, s.parent_id 
        FROM student_invoices inv 
        JOIN students s ON inv.student_id = s.id 
        WHERE inv.id = ?
    ");
    $stmt_inv->execute([$invoice_id]);
    $inv = $stmt_inv->fetch();

    if (!$inv) {
        set_flash('danger', 'Invalid student invoice selected.');
    } elseif ($amount <= 0) {
        set_flash('danger', 'Payment amount must be greater than ₦0.00.');
    } elseif ($amount > (float)$inv['balance']) {
        set_flash('danger', 'Payment amount exceeds outstanding balance of ' . format_currency($inv['balance']));
    } else {
        try {
            $pdo->beginTransaction();

            $payment_ref = generate_payment_reference();
            if (empty($transaction_reference)) {
                $transaction_reference = 'BURSARY_' . date('Ymd') . '_' . strtoupper(bin2hex(random_bytes(3)));
            }

            // Insert payment
            $stmt_p = $pdo->prepare("
                INSERT INTO payments (
                    invoice_id, student_id, parent_id, payment_reference, transaction_reference,
                    amount, payment_method, payment_channel, payment_status, verified_by, verified_at, notes
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'office', 'successful', ?, NOW(), ?)
            ");
            $stmt_p->execute([
                $inv['id'], $inv['student_id'], $inv['parent_id'], $payment_ref, $transaction_reference,
                $amount, $payment_method, $_SESSION['user_id'], $notes
            ]);
            $pay_id = (int)$pdo->lastInsertId();

            // Recalculate invoice
            recalculate_invoice((int)$inv['id']);

            // Issue official receipt
            $rec_num = generate_receipt_number();
            $stmt_r = $pdo->prepare("
                INSERT INTO receipts (receipt_number, payment_id, invoice_id, student_id, parent_id, amount, issued_date)
                VALUES (?, ?, ?, ?, ?, ?, CURDATE())
            ");
            $stmt_r->execute([$rec_num, $pay_id, $inv['id'], $inv['student_id'], $inv['parent_id'], $amount]);

            $pdo->commit();

            // Notify Parent
            send_broadcast_notification(
                (int)$_SESSION['user_id'],
                "Payment Received at Bursary: Receipt #{$rec_num}",
                "Assalamu Alaikum. We have received payment of " . format_currency($amount) . " for student {$inv['first_name']} {$inv['last_name']}. Official receipt #{$rec_num} is now available in your portal.",
                "selected_parent",
                (int)$inv['parent_id']
            );

            log_audit('BURSARY_PAYMENT_RECORDED', 'payments', $pay_id, "Recorded bursary payment of ₦{$amount} for {$inv['first_name']}. Receipt: {$rec_num}");

            set_flash('success', "Payment of " . format_currency($amount) . " recorded successfully! Receipt #{$rec_num} generated.");
            header('Location: ' . BASE_URL . '/admin/payments.php');
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('danger', "Failed to record payment: " . $e->getMessage());
        }
    }
}

// Fetch invoices with balance for the manual payment modal
$active_invoices = $pdo->query("
    SELECT inv.id, inv.invoice_number, inv.balance, s.first_name, s.last_name, s.student_id_number, c.class_name
    FROM student_invoices inv
    JOIN students s ON inv.student_id = s.id
    LEFT JOIN classes c ON s.current_class_id = c.id
    WHERE inv.balance > 0
    ORDER BY s.first_name ASC
")->fetchAll();

// Filters & Search
$status_filter = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT 
        p.*, 
        s.first_name, s.last_name, s.student_id_number,
        pt.full_name as parent_name, u.phone as parent_phone,
        r.receipt_number,
        inv.invoice_number,
        admin_u.username as verified_admin_name
    FROM payments p
    JOIN students s ON p.student_id = s.id
    JOIN parents pt ON p.parent_id = pt.id
    JOIN users u ON pt.user_id = u.id
    JOIN student_invoices inv ON p.invoice_id = inv.id
    LEFT JOIN receipts r ON p.id = r.payment_id
    LEFT JOIN users admin_u ON p.verified_by = admin_u.id
    WHERE 1=1
";
$params = [];
if ($status_filter !== 'all') {
    $sql .= " AND p.payment_status = ?";
    $params[] = $status_filter;
}
if (!empty($search)) {
    $sql .= " AND (p.payment_reference LIKE ? OR p.transaction_reference LIKE ? OR r.receipt_number LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR pt.full_name LIKE ?)";
    $term = "%{$search}%";
    $params = array_merge($params, [$term, $term, $term, $term, $term, $term]);
}
$sql .= " ORDER BY p.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$payments = $stmt->fetchAll();
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold text-islamic mb-1">
            <i class="bi bi-credit-card-2-front text-gold me-2"></i> All Transactions & Payments
        </h3>
        <p class="text-muted small mb-0">Complete historical financial log of all online gateway, bank transfer, cash, and POS transactions.</p>
    </div>
    <div class="mt-3 mt-md-0 d-flex gap-2">
        <button type="button" class="btn btn-gold btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#recordPaymentModal">
            <i class="bi bi-cash-stack me-1"></i> Record Cash / POS Payment
        </button>
        <a href="?export=csv" class="btn btn-outline-success btn-sm">
            <i class="bi bi-filetype-csv me-1"></i> Export to CSV
        </a>
    </div>
</div>

<!-- Search & Filters -->
<div class="card card-modern p-3 mb-4">
    <form method="GET" action="" class="row g-2 align-items-center">
        <div class="col-md-5">
            <div class="input-group">
                <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                <input type="text" name="search" class="form-control" value="<?= e($search) ?>" placeholder="Search reference, receipt, student, parent...">
            </div>
        </div>
        <div class="col-md-4">
            <select name="status" class="form-select">
                <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Transaction Statuses</option>
                <option value="successful" <?= $status_filter === 'successful' ? 'selected' : '' ?>>Successful / Verified</option>
                <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending Verification</option>
                <option value="rejected" <?= $status_filter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
            </select>
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-islamic w-100">Filter Records</button>
        </div>
    </form>
</div>

<!-- Transactions Table -->
<div class="card card-modern">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h5 class="mb-0 text-islamic fw-bold">Transactions (<?= count($payments) ?>)</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Reference</th>
                        <th>Student & Class</th>
                        <th>Parent & Phone</th>
                        <th>Amount (₦)</th>
                        <th>Method</th>
                        <th>Date & Time</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Receipt</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">No transactions found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($payments as $p): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="font-monospace fw-bold text-islamic small"><?= e($p['payment_reference']) ?></div>
                                    <?php if (!empty($p['transaction_reference'])): ?>
                                        <div class="small text-muted font-monospace" style="font-size: 0.72rem;">Ext: <?= e($p['transaction_reference']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-bold"><?= e($p['first_name'] . ' ' . $p['last_name']) ?></div>
                                    <small class="text-muted"><?= e($p['student_id_number'] ?? 'Pending') ?></small>
                                </td>
                                <td>
                                    <div><?= e($p['parent_name']) ?></div>
                                    <small class="text-muted"><?= e($p['parent_phone']) ?></small>
                                </td>
                                <td class="fw-bold text-dark fs-6"><?= format_currency($p['amount']) ?></td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        <?= e(str_replace('_', ' ', strtoupper($p['payment_method']))) ?>
                                    </span>
                                </td>
                                <td class="small text-muted"><?= format_date($p['created_at'], 'd M Y, h:i A') ?></td>
                                <td>
                                    <span class="badge bg-<?= $p['payment_status'] === 'successful' ? 'success' : ($p['payment_status'] === 'pending' ? 'warning text-dark' : 'danger') ?>">
                                        <?= ucfirst($p['payment_status']) ?>
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <?php if ($p['payment_status'] === 'successful' && !empty($p['receipt_number'])): ?>
                                        <a href="<?= BASE_URL ?>/parent/receipt.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-gold" target="_blank">
                                            <i class="bi bi-printer me-1"></i> Receipt
                                        </a>
                                    <?php elseif ($p['payment_status'] === 'pending'): ?>
                                        <a href="<?= BASE_URL ?>/admin/verify_payments.php" class="btn btn-sm btn-outline-warning">
                                            Verify
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Record Cash / POS Payment -->
<div class="modal fade" id="recordPaymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content text-start">
            <form method="POST" action="">
                <?= csrf_field() ?>
                <div class="modal-header bg-islamic text-white">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-cash-stack me-2 text-gold"></i> Record Bursary Payment (Cash / POS)
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Select Student Fee Invoice <span class="text-danger">*</span></label>
                        <select name="invoice_id" class="form-select" required>
                            <option value="">-- Choose Invoice with Pending Balance --</option>
                            <?php foreach ($active_invoices as $ai): ?>
                                <option value="<?= $ai['id'] ?>">
                                    <?= e($ai['first_name'] . ' ' . $ai['last_name']) ?> (<?= e($ai['student_id_number'] ?? 'ID Pending') ?>) - <?= e($ai['invoice_number']) ?> - Bal: <?= format_currency($ai['balance']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Amount Paid in Naira (₦) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light fw-bold">₦</span>
                                <input type="number" step="0.01" min="100" name="amount" class="form-control fw-bold" required placeholder="e.g. 25000">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Payment Channel <span class="text-danger">*</span></label>
                            <select name="payment_method" class="form-select" required>
                                <option value="cash">Cash at Bursary</option>
                                <option value="pos">POS Terminal Debit</option>
                                <option value="bank_transfer">Direct Bank Deposit / Transfer</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">External Reference / POS RRN (Optional)</label>
                        <input type="text" name="transaction_reference" class="form-control" placeholder="e.g. POS-RRN-992381 or Cash Receipt #">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Audit Notes</label>
                        <input type="text" name="notes" class="form-control" value="Paid in cash / POS at school bursary office.">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="record_manual_payment" value="1" class="btn btn-islamic btn-sm px-4">
                        Confirm and Issue Receipt
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
