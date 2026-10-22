<?php
$page_title = "Payment Verification";
$active_tab = "verify_payments";
require_once __DIR__ . '/../includes/admin_header.php';

$pdo = get_db_connection();

// Process Verification Approval
if (isset($_POST['confirm_payment']) && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $payment_id = (int)$_POST['payment_id'];
    $admin_notes = trim($_POST['admin_notes'] ?? 'Verified bank transfer received in school account.');

    try {
        $pdo->beginTransaction();

        // Fetch payment details
        $stmt_p = $pdo->prepare("
            SELECT p.*, s.first_name, s.last_name, s.student_id_number, pt.user_id, inv.invoice_number
            FROM payments p
            JOIN students s ON p.student_id = s.id
            JOIN parents pt ON p.parent_id = pt.id
            JOIN student_invoices inv ON p.invoice_id = inv.id
            WHERE p.id = ? AND p.payment_status = 'pending'
        ");
        $stmt_p->execute([$payment_id]);
        $pay = $stmt_p->fetch();

        if (!$pay) {
            throw new Exception("Payment claim not found or has already been verified.");
        }

        // 1. Update payment record
        $stmt_up = $pdo->prepare("
            UPDATE payments 
            SET payment_status = 'successful',
                verified_by = ?,
                verified_at = NOW(),
                notes = CONCAT(COALESCE(notes, ''), ' [Admin Note: ', ?, ']')
            WHERE id = ?
        ");
        $stmt_up->execute([$_SESSION['user_id'], $admin_notes, $payment_id]);

        // 2. Recalculate invoice
        recalculate_invoice((int)$pay['invoice_id']);

        // 3. Generate official receipt
        $rec_num = generate_receipt_number();
        $stmt_rec = $pdo->prepare("
            INSERT INTO receipts (receipt_number, payment_id, invoice_id, student_id, parent_id, amount, issued_date)
            VALUES (?, ?, ?, ?, ?, ?, CURDATE())
        ");
        $stmt_rec->execute([$rec_num, $payment_id, $pay['invoice_id'], $pay['student_id'], $pay['parent_id'], $pay['amount']]);

        $pdo->commit();

        // 4. Send Notification to Parent
        send_broadcast_notification(
            (int)$_SESSION['user_id'],
            "Payment Confirmed: Receipt #{$rec_num}",
            "Assalamu Alaikum. Your payment claim of " . format_currency($pay['amount']) . " for student {$pay['first_name']} {$pay['last_name']} has been verified and confirmed. Official receipt #{$rec_num} is ready in your portal.",
            "selected_parent",
            (int)$pay['parent_id']
        );

        log_audit('PAYMENT_VERIFIED', 'payments', $payment_id, "Verified payment of ₦{$pay['amount']} for {$pay['first_name']}. Receipt: {$rec_num}");

        set_flash('success', "Payment #{$pay['payment_reference']} verified successfully! Official receipt {$rec_num} issued.");
        header('Location: ' . BASE_URL . '/admin/verify_payments.php');
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        set_flash('danger', "Verification error: " . $e->getMessage());
    }
}

// Process Rejection
if (isset($_POST['reject_payment']) && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $payment_id = (int)$_POST['payment_id'];
    $rejection_reason = trim($_POST['rejection_reason'] ?? 'Payment could not be verified in the school bank account.');

    try {
        $stmt_p = $pdo->prepare("
            SELECT p.*, s.first_name, s.last_name, pt.user_id 
            FROM payments p 
            JOIN students s ON p.student_id = s.id 
            JOIN parents pt ON p.parent_id = pt.id 
            WHERE p.id = ? AND p.payment_status = 'pending'
        ");
        $stmt_p->execute([$payment_id]);
        $pay = $stmt_p->fetch();

        if ($pay) {
            $stmt_rej = $pdo->prepare("
                UPDATE payments 
                SET payment_status = 'rejected',
                    rejection_reason = ?,
                    verified_by = ?,
                    verified_at = NOW()
                WHERE id = ?
            ");
            $stmt_rej->execute([$rejection_reason, $_SESSION['user_id'], $payment_id]);

            // Notify Parent
            send_broadcast_notification(
                (int)$_SESSION['user_id'],
                "Payment Claim Rejected (Ref: {$pay['payment_reference']})",
                "Your payment claim of " . format_currency($pay['amount']) . " for {$pay['first_name']} was rejected. Reason: {$rejection_reason}. Please verify your bank debit and contact the school bursary.",
                "selected_parent",
                (int)$pay['parent_id']
            );

            log_audit('PAYMENT_REJECTED', 'payments', $payment_id, "Rejected payment claim ₦{$pay['amount']}. Reason: {$rejection_reason}");

            set_flash('warning', "Payment claim has been rejected and the parent has been notified.");
            header('Location: ' . BASE_URL . '/admin/verify_payments.php');
            exit;
        }
    } catch (Exception $e) {
        set_flash('danger', "Error rejecting payment: " . $e->getMessage());
    }
}

// Fetch pending payments
$stmt_pending = $pdo->query("
    SELECT p.*, s.first_name, s.last_name, s.student_id_number, c.class_name,
           pt.full_name as parent_name, u.phone as parent_phone, inv.invoice_number, inv.total_amount, inv.balance
    FROM payments p
    JOIN students s ON p.student_id = s.id
    LEFT JOIN classes c ON s.current_class_id = c.id
    JOIN parents pt ON p.parent_id = pt.id
    JOIN users u ON pt.user_id = u.id
    JOIN student_invoices inv ON p.invoice_id = inv.id
    WHERE p.payment_status = 'pending'
    ORDER BY p.id ASC
");
$pending_list = $stmt_pending->fetchAll();
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold text-islamic mb-1">
            <i class="bi bi-patch-check-fill text-gold me-2"></i> Payment Verification Module
        </h3>
        <p class="text-muted small mb-0">Review pending bank transfers and payment claims submitted by parents, verify against bank statements, and issue receipts.</p>
    </div>
    <div class="mt-3 mt-md-0">
        <a href="<?= BASE_URL ?>/admin/payments.php" class="btn btn-outline-islamic btn-sm">
            <i class="bi bi-clock-history me-1"></i> View All Confirmed Payments
        </a>
    </div>
</div>

<div class="card card-modern">
    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center py-3 gap-2">
        <div>
            <h5 class="mb-0 text-islamic fw-bold">
                Pending Claims for Review (<?= count($pending_list) ?>)
            </h5>
            <span class="badge bg-danger mt-1"><?= count($pending_list) ?> Awaiting Verification</span>
        </div>
        <div class="table-search-wrap" style="width: 250px;">
            <input type="text" class="form-control form-control-sm table-search-input pe-5" data-target-table="verify_payments_table" placeholder="Filter claims...">
            <span class="table-search-results"></span>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="verify_payments_table">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Reference & Date</th>
                        <th>Student & Class</th>
                        <th>Parent & Phone</th>
                        <th>Amount Claimed (₦)</th>
                        <th>Bank / Ext Ref</th>
                        <th>Transfer Proof</th>
                        <th class="text-end pe-4">Verification Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($pending_list)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-check-circle-fill fs-1 text-success d-block mb-2"></i>
                                <h5 class="fw-bold text-islamic">No Pending Payments</h5>
                                <p class="small mb-0">All submitted payment claims have been reviewed and verified.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($pending_list as $pay): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center">
                                        <span class="font-monospace fw-bold small text-islamic"><?= e($pay['payment_reference']) ?></span>
                                        <button type="button" class="copy-btn py-0 px-1 ms-1" data-copy="<?= e($pay['payment_reference']) ?>" title="Copy Ref">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </div>
                                    <small class="text-muted"><?= format_date($pay['created_at'], 'd M Y, h:i A') ?></small>
                                </td>
                                <td>
                                    <div class="fw-bold"><?= e($pay['first_name'] . ' ' . $pay['last_name']) ?></div>
                                    <small class="text-muted"><?= e($pay['class_name'] ?? 'Class') ?> &bull; <?= e($pay['student_id_number'] ?? 'Pending') ?></small>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?= e($pay['parent_name']) ?></div>
                                    <div class="d-flex align-items-center">
                                        <small class="text-muted"><a href="tel:<?= e($pay['parent_phone']) ?>" class="text-decoration-none"><?= e($pay['parent_phone']) ?></a></small>
                                        <button type="button" class="copy-btn py-0 px-1 ms-1" data-copy="<?= e($pay['parent_phone']) ?>" title="Copy Phone">
                                            <i class="bi bi-telephone"></i>
                                        </button>
                                    </div>
                                </td>
                                <td class="fw-bold text-dark fs-6"><?= format_currency($pay['amount']) ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <span class="badge bg-light text-dark border font-monospace">
                                            <?= e($pay['transaction_reference'] ?: 'N/A') ?>
                                        </span>
                                        <?php if (!empty($pay['transaction_reference'])): ?>
                                            <button type="button" class="copy-btn py-0 px-1 ms-1" data-copy="<?= e($pay['transaction_reference']) ?>" title="Copy Bank Ref">
                                                <i class="bi bi-clipboard"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($pay['proof_file'])): ?>
                                        <a href="<?= UPLOADS_URL ?>/receipts/<?= e($pay['proof_file']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-file-earmark-image me-1"></i> View Slip
                                        </a>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border">No slip uploaded</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <!-- Confirm Payment Modal Button -->
                                    <button type="button" class="btn btn-sm btn-success me-1" data-bs-toggle="modal" data-bs-target="#confirmPayModal<?= $pay['id'] ?>">
                                        <i class="bi bi-check2-circle me-1"></i> Verify & Issue Receipt
                                    </button>
                                    <!-- Reject Payment Modal Button -->
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectPayModal<?= $pay['id'] ?>">
                                        <i class="bi bi-x-circle"></i>
                                    </button>
                                </td>
                            </tr>

                            <!-- Modal Confirm -->
                            <div class="modal fade" id="confirmPayModal<?= $pay['id'] ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content text-start">
                                        <form method="POST" action="">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="payment_id" value="<?= $pay['id'] ?>">

                                            <div class="modal-header bg-islamic text-white">
                                                <h5 class="modal-title fw-bold">
                                                    <i class="bi bi-check-circle me-2 text-gold"></i> Confirm Payment Verification
                                                </h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body p-4">
                                                <div class="p-3 bg-light rounded-3 mb-3 small">
                                                    <div class="d-flex justify-content-between mb-1">
                                                        <span class="text-muted">Student:</span>
                                                        <strong><?= e($pay['first_name'] . ' ' . $pay['last_name']) ?></strong>
                                                    </div>
                                                    <div class="d-flex justify-content-between mb-1">
                                                        <span class="text-muted">Amount Claimed:</span>
                                                        <strong class="text-success fs-6"><?= format_currency($pay['amount']) ?></strong>
                                                    </div>
                                                    <div class="d-flex justify-content-between mb-1">
                                                        <span class="text-muted">Bank Reference:</span>
                                                        <strong class="font-monospace"><?= e($pay['transaction_reference']) ?></strong>
                                                    </div>
                                                    <div class="d-flex justify-content-between">
                                                        <span class="text-muted">Invoice No:</span>
                                                        <span><?= e($pay['invoice_number']) ?></span>
                                                    </div>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold">Audit / Verification Notes</label>
                                                    <input type="text" name="admin_notes" class="form-control" value="Confirmed in school Jaiz Bank statement." required>
                                                </div>

                                                <p class="small text-muted mb-0">
                                                    Confirming this payment will immediately update the student's fee invoice balance, issue a stamped receipt, and send an in-app confirmation notification to the parent.
                                                </p>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" name="confirm_payment" value="1" class="btn btn-islamic btn-sm px-4">
                                                    Verify and Issue Receipt
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <!-- Modal Reject -->
                            <div class="modal fade" id="rejectPayModal<?= $pay['id'] ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content text-start">
                                        <form method="POST" action="">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="payment_id" value="<?= $pay['id'] ?>">

                                            <div class="modal-header bg-danger text-white">
                                                <h5 class="modal-title fw-bold">
                                                    <i class="bi bi-x-circle me-2"></i> Reject Payment Claim
                                                </h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body p-4">
                                                <p class="small text-muted mb-3">
                                                    Rejecting payment claim of <strong><?= format_currency($pay['amount']) ?></strong> for <strong><?= e($pay['first_name'] . ' ' . $pay['last_name']) ?></strong>.
                                                </p>
                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold">Reason for Rejection <span class="text-danger">*</span></label>
                                                    <textarea name="rejection_reason" class="form-control" rows="3" required placeholder="e.g. Transaction reference not found on school bank statement or amount discrepancy."></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" name="reject_payment" value="1" class="btn btn-danger btn-sm">
                                                    Reject Claim
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
