<?php
$page_title = "Payment History";
$active_tab = "payment_history";
require_once __DIR__ . '/../includes/parent_header.php';

$pdo = get_db_connection();
$parent_id = (int)$parent['id'];

$stmt = $pdo->prepare("
    SELECT 
        p.*, 
        s.first_name, s.last_name, s.student_id_number,
        r.receipt_number,
        inv.invoice_number,
        sess.session_name,
        t.term_name
    FROM payments p
    JOIN students s ON p.student_id = s.id
    JOIN student_invoices inv ON p.invoice_id = inv.id
    JOIN academic_sessions sess ON inv.session_id = sess.id
    JOIN academic_terms t ON inv.term_id = t.id
    WHERE p.parent_id = ?
    ORDER BY p.id DESC
");
$stmt->execute([$parent_id]);
$payments = $stmt->fetchAll();
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold text-islamic mb-1">
            <i class="bi bi-clock-history text-gold me-2"></i> Payment Records & Receipts
        </h3>
        <p class="text-muted small mb-0">Complete historical log of all school fee transactions and payment receipts.</p>
    </div>
    <div class="mt-3 mt-md-0 d-flex gap-2">
        <input type="text" class="form-control form-control-sm table-search-input" data-target-table="parent_payments_table" placeholder="Filter payments..." style="width: 220px;">
        <a href="<?= BASE_URL ?>/parent/pay_fee.php" class="btn btn-gold btn-sm shadow-sm">
            <i class="bi bi-credit-card me-1"></i> Make New Payment
        </a>
    </div>
</div>

<div class="card card-modern">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h5 class="mb-0 text-islamic fw-bold">
            <i class="bi bi-receipt-cutoff text-gold me-2"></i> All Transactions (<?= count($payments) ?>)
        </h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="parent_payments_table">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Reference</th>
                        <th>Child / Student ID</th>
                        <th>Session & Term</th>
                        <th>Amount Paid (₦)</th>
                        <th>Method</th>
                        <th>Date & Time</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Receipt</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-cash-stack fs-1 d-block mb-2 text-muted"></i>
                                No payment records found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($payments as $pay): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="font-monospace fw-bold small text-islamic"><?= e($pay['payment_reference']) ?></div>
                                    <?php if (!empty($pay['transaction_reference'])): ?>
                                        <div class="small text-muted font-monospace" style="font-size: 0.75rem;">Ext: <?= e($pay['transaction_reference']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="fw-bold"><?= e($pay['first_name'] . ' ' . $pay['last_name']) ?></div>
                                    <small class="text-muted"><?= e($pay['student_id_number'] ?? 'ID Pending') ?></small>
                                </td>
                                <td>
                                    <?= e($pay['session_name']) ?>
                                    <div class="small text-muted"><?= e($pay['term_name']) ?></div>
                                </td>
                                <td class="fw-bold text-dark fs-6"><?= format_currency($pay['amount']) ?></td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        <?= e(str_replace('_', ' ', strtoupper($pay['payment_method']))) ?>
                                    </span>
                                </td>
                                <td class="small text-muted"><?= format_date($pay['created_at'], 'd M Y, h:i A') ?></td>
                                <td>
                                    <?php if ($pay['payment_status'] === 'successful'): ?>
                                        <span class="badge bg-success p-2">
                                            <i class="bi bi-check-circle-fill me-1"></i> Verified & Paid
                                        </span>
                                    <?php elseif ($pay['payment_status'] === 'pending'): ?>
                                        <span class="badge bg-warning text-dark p-2" title="Our admin is reviewing your bank transfer">
                                            <i class="bi bi-hourglass-split me-1"></i> Pending Verification
                                        </span>
                                    <?php elseif ($pay['payment_status'] === 'rejected'): ?>
                                        <span class="badge bg-danger p-2" title="<?= e($pay['rejection_reason']) ?>">
                                            <i class="bi bi-x-circle-fill me-1"></i> Rejected
                                        </span>
                                        <?php if (!empty($pay['rejection_reason'])): ?>
                                            <div class="small text-danger mt-1" style="font-size: 0.75rem;"><?= e($pay['rejection_reason']) ?></div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-secondary p-2"><?= e(ucfirst($pay['payment_status'])) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <?php if ($pay['payment_status'] === 'successful' && !empty($pay['receipt_number'])): ?>
                                        <a href="<?= BASE_URL ?>/parent/receipt.php?id=<?= $pay['id'] ?>" class="btn btn-sm btn-gold" target="_blank">
                                            <i class="bi bi-printer me-1"></i> View Receipt
                                        </a>
                                    <?php elseif ($pay['payment_status'] === 'pending'): ?>
                                        <span class="text-muted small italic">Pending</span>
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

<?php require_once __DIR__ . '/../includes/parent_footer.php'; ?>
