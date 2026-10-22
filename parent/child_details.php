<?php
$page_title = "Child Profile & Academic Fees";
$active_tab = "my_children";
require_once __DIR__ . '/../includes/parent_header.php';

$pdo = get_db_connection();
$parent_id = (int)$parent['id'];
$student_id = (int)($_GET['id'] ?? 0);

// Fetch child and ensure ownership
$stmt = $pdo->prepare("
    SELECT s.*, c.class_name as current_class_name, rc.class_name as requested_class_name
    FROM students s
    LEFT JOIN classes c ON s.current_class_id = c.id
    LEFT JOIN classes rc ON s.requested_class_id = rc.id
    WHERE s.id = ? AND s.parent_id = ?
");
$stmt->execute([$student_id, $parent_id]);
$child = $stmt->fetch();

if (!$child) {
    set_flash('danger', 'Child record not found or access denied.');
    header('Location: ' . BASE_URL . '/parent/my_children.php');
    exit;
}

// Invoices for this student
$stmt_inv = $pdo->prepare("
    SELECT inv.*, s.session_name, t.term_name
    FROM student_invoices inv
    JOIN academic_sessions s ON inv.session_id = s.id
    JOIN academic_terms t ON inv.term_id = t.id
    WHERE inv.student_id = ?
    ORDER BY inv.id DESC
");
$stmt_inv->execute([$student_id]);
$invoices = $stmt_inv->fetchAll();

// Payments for this student
$stmt_pay = $pdo->prepare("
    SELECT p.*, r.receipt_number
    FROM payments p
    LEFT JOIN receipts r ON p.id = r.payment_id
    WHERE p.student_id = ?
    ORDER BY p.id DESC
");
$stmt_pay->execute([$student_id]);
$payments = $stmt_pay->fetchAll();

$age = date_diff(date_create($child['date_of_birth']), date_create('today'))->y;
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <a href="<?= BASE_URL ?>/parent/my_children.php" class="text-decoration-none small text-muted mb-1 d-inline-block">
            <i class="bi bi-arrow-left"></i> Back to My Children
        </a>
        <h3 class="fw-bold text-islamic mb-0">
            <?= e($child['first_name'] . ' ' . $child['last_name'] . ' ' . ($child['other_name'] ?? '')) ?>
        </h3>
    </div>
    <div class="mt-3 mt-md-0 d-flex gap-2">
        <a href="<?= BASE_URL ?>/parent/pay_fee.php?student_id=<?= $child['id'] ?>" class="btn btn-gold shadow-sm">
            <i class="bi bi-credit-card me-1"></i> Pay Child's Fees
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Bio Card -->
    <div class="col-lg-4">
        <div class="card card-modern p-4 mb-4">
            <div class="text-center mb-3">
                <div class="stat-icon bg-islamic-soft text-islamic mx-auto mb-2" style="width: 68px; height: 68px; font-size: 2rem;">
                    <i class="bi bi-person-fill"></i>
                </div>
                <h5 class="fw-bold text-islamic mb-1">
                    <?= e($child['first_name'] . ' ' . $child['last_name']) ?>
                </h5>
                <span class="badge bg-gold">
                    <?= $child['student_id_number'] ? e($child['student_id_number']) : 'Student ID Pending' ?>
                </span>
            </div>

            <hr class="my-3">

            <div class="small">
                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span class="text-muted">Registration Status:</span>
                    <span>
                        <?php if ($child['registration_status'] === 'approved'): ?>
                            <span class="badge bg-success">Approved</span>
                        <?php elseif ($child['registration_status'] === 'rejected'): ?>
                            <span class="badge bg-danger">Rejected</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">Pending</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span class="text-muted">Enrolment Status:</span>
                    <span class="badge bg-light text-dark border"><?= e(ucfirst($child['enrolment_status'])) ?></span>
                </div>
                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span class="text-muted">Gender:</span>
                    <strong><?= e($child['gender']) ?></strong>
                </div>
                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span class="text-muted">Age / DOB:</span>
                    <strong><?= $age ?> yrs (<?= format_date($child['date_of_birth']) ?>)</strong>
                </div>
                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span class="text-muted">Assigned Class:</span>
                    <strong class="text-islamic"><?= e($child['current_class_name'] ?? 'Not Assigned Yet') ?></strong>
                </div>
                <div class="d-flex justify-content-between py-1 border-bottom">
                    <span class="text-muted">Requested Class:</span>
                    <span><?= e($child['requested_class_name'] ?? 'N/A') ?></span>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span class="text-muted">Admission Date:</span>
                    <span><?= format_date($child['admission_date']) ?></span>
                </div>
            </div>

            <?php if (!empty($child['previous_islamiyya_education'])): ?>
                <div class="mt-3 p-3 bg-light rounded-3 small">
                    <strong class="d-block text-islamic mb-1">Previous Islamiyya Background:</strong>
                    <div class="text-muted"><?= nl2br(e($child['previous_islamiyya_education'])) ?></div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Invoices & Payment Records -->
    <div class="col-lg-8">
        <!-- Invoices List -->
        <div class="card card-modern mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <h5 class="mb-0 text-islamic fw-bold">
                    <i class="bi bi-receipt text-gold me-2"></i> Termly Fee Invoices
                </h5>
                <span class="badge bg-islamic-soft text-islamic"><?= count($invoices) ?> Invoices</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Invoice #</th>
                                <th>Session & Term</th>
                                <th>Total Fee</th>
                                <th>Amount Paid</th>
                                <th>Balance (₦)</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($invoices)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">
                                        No invoices generated for this student yet. Invoices are generated once registration is approved.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($invoices as $inv): ?>
                                    <tr>
                                        <td class="ps-4 font-monospace fw-bold small"><?= e($inv['invoice_number']) ?></td>
                                        <td>
                                            <?= e($inv['session_name']) ?>
                                            <div class="small text-muted"><?= e($inv['term_name']) ?></div>
                                        </td>
                                        <td class="fw-bold"><?= format_currency($inv['total_amount']) ?></td>
                                        <td class="text-success"><?= format_currency($inv['amount_paid']) ?></td>
                                        <td class="fw-bold <?= $inv['balance'] > 0 ? 'text-danger' : 'text-muted' ?>">
                                            <?= format_currency($inv['balance']) ?>
                                        </td>
                                        <td>
                                            <?php if ($inv['status'] === 'paid'): ?>
                                                <span class="badge bg-success">Paid</span>
                                            <?php elseif ($inv['status'] === 'partially_paid'): ?>
                                                <span class="badge bg-warning text-dark">Partial</span>
                                            <?php elseif ($inv['status'] === 'overdue'): ?>
                                                <span class="badge bg-danger">Overdue</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Unpaid</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <?php if ($inv['balance'] > 0): ?>
                                                <a href="<?= BASE_URL ?>/parent/pay_fee.php?student_id=<?= $child['id'] ?>&invoice_id=<?= $inv['id'] ?>" class="btn btn-sm btn-gold">
                                                    Pay
                                                </a>
                                            <?php else: ?>
                                                <span class="text-success small fw-bold"><i class="bi bi-check-circle"></i> Cleared</span>
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

        <!-- Payments for this Student -->
        <div class="card card-modern">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <h5 class="mb-0 text-islamic fw-bold">
                    <i class="bi bi-credit-card-fill text-gold me-2"></i> Payment History for <?= e($child['first_name']) ?>
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Reference</th>
                                <th>Amount</th>
                                <th>Method</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Receipt</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($payments)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">No payments recorded for this child yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($payments as $pay): ?>
                                    <tr>
                                        <td class="ps-4 font-monospace small fw-bold"><?= e($pay['payment_reference']) ?></td>
                                        <td class="fw-bold text-dark"><?= format_currency($pay['amount']) ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= e(str_replace('_', ' ', strtoupper($pay['payment_method']))) ?></span></td>
                                        <td class="small text-muted"><?= format_date($pay['created_at'], 'd M Y, h:i A') ?></td>
                                        <td>
                                            <?php if ($pay['payment_status'] === 'successful'): ?>
                                                <span class="badge bg-success">Successful</span>
                                            <?php elseif ($pay['payment_status'] === 'pending'): ?>
                                                <span class="badge bg-warning text-dark">Verification Pending</span>
                                            <?php elseif ($pay['payment_status'] === 'rejected'): ?>
                                                <span class="badge bg-danger" title="<?= e($pay['rejection_reason']) ?>">Rejected</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary"><?= e(ucfirst($pay['payment_status'])) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <?php if ($pay['payment_status'] === 'successful' && !empty($pay['receipt_number'])): ?>
                                                <a href="<?= BASE_URL ?>/parent/receipt.php?id=<?= $pay['id'] ?>" class="btn btn-sm btn-gold" target="_blank">
                                                    <i class="bi bi-printer me-1"></i> Receipt
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
    </div>
</div>

<?php require_once __DIR__ . '/../includes/parent_footer.php'; ?>
