<?php
$page_title = "School Fees Overview";
$active_tab = "fees";
require_once __DIR__ . '/../includes/parent_header.php';

$pdo = get_db_connection();
$parent_id = (int)$parent['id'];
$cur_term = get_current_session_term();

// Fetch all invoices for this parent's children
$stmt = $pdo->prepare("
    SELECT 
        inv.*,
        s.first_name, s.last_name, s.student_id_number,
        c.class_name,
        sess.session_name,
        t.term_name
    FROM student_invoices inv
    JOIN students s ON inv.student_id = s.id
    LEFT JOIN classes c ON s.current_class_id = c.id
    JOIN academic_sessions sess ON inv.session_id = sess.id
    JOIN academic_terms t ON inv.term_id = t.id
    WHERE s.parent_id = ?
    ORDER BY inv.id DESC
");
$stmt->execute([$parent_id]);
$invoices = $stmt->fetchAll();

// Total Summary
$total_due = 0;
$total_paid = 0;
$total_balance = 0;
foreach ($invoices as $inv) {
    $total_due += (float)$inv['total_amount'];
    $total_paid += (float)$inv['amount_paid'];
    $total_balance += (float)$inv['balance'];
}
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold text-islamic mb-1">
            <i class="bi bi-wallet2 text-gold me-2"></i> School Fees Summary
        </h3>
        <p class="text-muted small mb-0">Overview of academic term fee assessments and balances for your children.</p>
    </div>
    <div class="mt-3 mt-md-0">
        <a href="<?= BASE_URL ?>/parent/pay_fee.php" class="btn btn-gold shadow-sm">
            <i class="bi bi-credit-card me-1"></i> Make Fee Payment
        </a>
    </div>
</div>

<!-- Summary Row -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card stat-card p-3">
            <span class="text-muted small fw-bold text-uppercase">Total Assessed Fees</span>
            <h3 class="fw-bold text-dark mt-1 mb-0"><?= format_currency($total_due) ?></h3>
            <small class="text-muted">All active child invoices</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card gold-border p-3">
            <span class="text-muted small fw-bold text-uppercase">Total Paid</span>
            <h3 class="fw-bold text-success mt-1 mb-0"><?= format_currency($total_paid) ?></h3>
            <small class="text-success"><i class="bi bi-check-circle"></i> Confirmed payments</small>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card danger-border p-3">
            <span class="text-muted small fw-bold text-uppercase">Total Outstanding Balance</span>
            <h3 class="fw-bold text-danger mt-1 mb-0"><?= format_currency($total_balance) ?></h3>
            <small class="text-danger"><?= $total_balance > 0 ? 'Pending payment' : 'All clear' ?></small>
        </div>
    </div>
</div>

<!-- Invoices Table -->
<div class="card card-modern">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h5 class="mb-0 text-islamic fw-bold">
            <i class="bi bi-file-earmark-text text-gold me-2"></i> Fee Invoices Breakdown
        </h5>
        <span class="badge bg-islamic-soft text-islamic"><?= count($invoices) ?> Total Records</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Child (Student ID)</th>
                        <th>Class / Level</th>
                        <th>Session & Term</th>
                        <th>Total Fee (₦)</th>
                        <th>Paid (₦)</th>
                        <th>Balance (₦)</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Pay</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($invoices)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="bi bi-wallet-x fs-1 d-block mb-2"></i>
                                No fee invoices found. Invoices are generated once child registration is approved by the administration.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($invoices as $inv): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-islamic"><?= e($inv['first_name'] . ' ' . $inv['last_name']) ?></div>
                                    <small class="text-muted"><?= e($inv['student_id_number'] ?? 'ID Pending') ?></small>
                                </td>
                                <td><?= e($inv['class_name'] ?? 'Not Assigned') ?></td>
                                <td>
                                    <?= e($inv['session_name']) ?>
                                    <div class="small text-muted"><?= e($inv['term_name']) ?></div>
                                </td>
                                <td class="fw-bold"><?= format_currency($inv['total_amount']) ?></td>
                                <td class="text-success"><?= format_currency($inv['amount_paid']) ?></td>
                                <td class="fw-bold <?= $inv['balance'] > 0 ? 'text-danger' : 'text-muted' ?>">
                                    <?= format_currency($inv['balance']) ?>
                                </td>
                                <td class="small text-muted"><?= format_date($inv['due_date']) ?></td>
                                <td>
                                    <?php if ($inv['status'] === 'paid'): ?>
                                        <span class="badge bg-success">Paid in Full</span>
                                    <?php elseif ($inv['status'] === 'partially_paid'): ?>
                                        <span class="badge bg-warning text-dark">Partially Paid</span>
                                    <?php elseif ($inv['status'] === 'overdue'): ?>
                                        <span class="badge bg-danger">Overdue</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Unpaid</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <?php if ($inv['balance'] > 0): ?>
                                        <a href="<?= BASE_URL ?>/parent/pay_fee.php?student_id=<?= $inv['student_id'] ?>&invoice_id=<?= $inv['id'] ?>" class="btn btn-sm btn-gold">
                                            Pay Fee
                                        </a>
                                    <?php else: ?>
                                        <span class="badge bg-success-subtle text-success border border-success"><i class="bi bi-check-all"></i> Cleared</span>
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
