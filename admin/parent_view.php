<?php
$page_title = "Parent Profile";
$active_tab = "parents";
require_once __DIR__ . '/../includes/admin_header.php';

$pdo = get_db_connection();
$parent_id = (int)($_GET['id'] ?? 0);

// Fetch parent
$stmt = $pdo->prepare("
    SELECT p.*, u.username, u.email, u.phone, u.status, u.last_login, u.created_at as user_created_at
    FROM parents p
    JOIN users u ON p.user_id = u.id
    WHERE p.id = ?
");
$stmt->execute([$parent_id]);
$parent_info = $stmt->fetch();

if (!$parent_info) {
    set_flash('danger', 'Parent record not found.');
    header('Location: ' . BASE_URL . '/admin/parents.php');
    exit;
}

// Edit Parent Details POST
if (isset($_POST['update_parent_details']) && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    $occupation = trim($_POST['occupation']);
    $residential_address = trim($_POST['residential_address']);
    $emergency_contact_name = trim($_POST['emergency_contact_name']);
    $emergency_contact_phone = trim($_POST['emergency_contact_phone']);

    try {
        $pdo->beginTransaction();
        $stmt_u = $pdo->prepare("UPDATE users SET phone = ?, email = ? WHERE id = ?");
        $stmt_u->execute([$phone, !empty($email) ? $email : null, $parent_info['user_id']]);

        $stmt_p = $pdo->prepare("
            UPDATE parents 
            SET full_name = ?, occupation = ?, residential_address = ?, emergency_contact_name = ?, emergency_contact_phone = ?
            WHERE id = ?
        ");
        $stmt_p->execute([$full_name, $occupation, $residential_address, $emergency_contact_name, $emergency_contact_phone, $parent_id]);
        $pdo->commit();

        log_audit('ADMIN_UPDATED_PARENT', 'parents', $parent_id, "Admin updated details for parent {$full_name}");
        set_flash('success', "Parent details updated successfully.");
        header('Location: ' . BASE_URL . '/admin/parent_view.php?id=' . $parent_id);
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        set_flash('danger', "Update failed: " . $e->getMessage());
    }
}

// Fetch children
$stmt_ch = $pdo->prepare("
    SELECT s.*, c.class_name as current_class_name, rc.class_name as requested_class_name,
           COALESCE(SUM(inv.balance), 0) as fee_balance
    FROM students s
    LEFT JOIN classes c ON s.current_class_id = c.id
    LEFT JOIN classes rc ON s.requested_class_id = rc.id
    LEFT JOIN student_invoices inv ON s.id = inv.student_id
    WHERE s.parent_id = ?
    GROUP BY s.id
    ORDER BY s.id DESC
");
$stmt_ch->execute([$parent_id]);
$children = $stmt_ch->fetchAll();

// Fetch payments
$stmt_pay = $pdo->prepare("
    SELECT p.*, s.first_name, s.last_name, s.student_id_number, r.receipt_number
    FROM payments p
    JOIN students s ON p.student_id = s.id
    LEFT JOIN receipts r ON p.id = r.payment_id
    WHERE p.parent_id = ?
    ORDER BY p.id DESC
");
$stmt_pay->execute([$parent_id]);
$payments = $stmt_pay->fetchAll();
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <a href="<?= BASE_URL ?>/admin/parents.php" class="text-decoration-none small text-muted mb-1 d-inline-block">
            <i class="bi bi-arrow-left"></i> Back to Parents List
        </a>
        <h3 class="fw-bold text-islamic mb-0">
            <?= e($parent_info['full_name']) ?>
        </h3>
    </div>
    <div class="mt-3 mt-md-0 d-flex gap-2">
        <a href="<?= BASE_URL ?>/admin/notifications.php?target_parent_id=<?= $parent_info['id'] ?>" class="btn btn-islamic btn-sm">
            <i class="bi bi-chat-left-text me-1"></i> Send Notification
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Profile Card & Edit Form -->
    <div class="col-lg-4">
        <div class="card card-modern p-4 mb-4">
            <div class="text-center mb-3">
                <div class="stat-icon bg-islamic-soft text-islamic mx-auto mb-2" style="width: 60px; height: 60px; font-size: 1.8rem;">
                    <i class="bi bi-person-fill"></i>
                </div>
                <h5 class="fw-bold text-islamic mb-0"><?= e($parent_info['full_name']) ?></h5>
                <small class="text-muted">Username: @<?= e($parent_info['username']) ?></small>
                <div class="mt-2">
                    <span class="badge bg-<?= $parent_info['status'] === 'active' ? 'success' : 'warning' ?>">
                        <?= ucfirst($parent_info['status']) ?>
                    </span>
                    <span class="badge bg-gold"><?= count($children) ?> Child(ren)</span>
                </div>
            </div>

            <hr class="my-3">

            <form method="POST" action="">
                <?= csrf_field() ?>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Full Name</label>
                    <input type="text" name="full_name" class="form-control form-control-sm" value="<?= e($parent_info['full_name']) ?>" required>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Phone Number</label>
                    <input type="tel" name="phone" class="form-control form-control-sm" value="<?= e($parent_info['phone']) ?>" required>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Email Address</label>
                    <input type="email" name="email" class="form-control form-control-sm" value="<?= e($parent_info['email']) ?>">
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Occupation</label>
                    <input type="text" name="occupation" class="form-control form-control-sm" value="<?= e($parent_info['occupation']) ?>">
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Residential Address</label>
                    <textarea name="residential_address" class="form-control form-control-sm" rows="2" required><?= e($parent_info['residential_address']) ?></textarea>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-semibold">Emergency Contact Person</label>
                    <input type="text" name="emergency_contact_name" class="form-control form-control-sm" value="<?= e($parent_info['emergency_contact_name']) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Emergency Contact Phone</label>
                    <input type="tel" name="emergency_contact_phone" class="form-control form-control-sm" value="<?= e($parent_info['emergency_contact_phone']) ?>">
                </div>
                <button type="submit" name="update_parent_details" value="1" class="btn btn-islamic btn-sm w-100">
                    <i class="bi bi-save me-1"></i> Save Profile Details
                </button>
            </form>
        </div>
    </div>

    <!-- Children & Payments -->
    <div class="col-lg-8">
        <!-- Associated Children -->
        <div class="card card-modern mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <h5 class="mb-0 text-islamic fw-bold">
                    <i class="bi bi-mortarboard-fill text-gold me-2"></i> Enrolled Children (<?= count($children) ?>)
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Student ID / Name</th>
                                <th>Gender / Age</th>
                                <th>Class</th>
                                <th>Fee Debt (₦)</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($children)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">No children registered under this parent yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($children as $ch): ?>
                                    <?php $age = date_diff(date_create($ch['date_of_birth']), date_create('today'))->y; ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-bold text-islamic"><?= e($ch['first_name'] . ' ' . $ch['last_name']) ?></div>
                                            <small class="text-muted"><?= e($ch['student_id_number'] ?? 'Pending Approval') ?></small>
                                        </td>
                                        <td><?= e($ch['gender']) ?>, <?= $age ?> yrs</td>
                                        <td><?= e($ch['current_class_name'] ?? $ch['requested_class_name']) ?></td>
                                        <td class="fw-bold <?= $ch['fee_balance'] > 0 ? 'text-danger' : 'text-success' ?>">
                                            <?= format_currency($ch['fee_balance']) ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $ch['registration_status'] === 'approved' ? 'success' : 'warning' ?>">
                                                <?= ucfirst($ch['registration_status']) ?>
                                            </span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <a href="<?= BASE_URL ?>/admin/student_view.php?id=<?= $ch['id'] ?>" class="btn btn-sm btn-outline-islamic">
                                                View
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

        <!-- Payment History -->
        <div class="card card-modern">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <h5 class="mb-0 text-islamic fw-bold">
                    <i class="bi bi-clock-history text-gold me-2"></i> Payment Records
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Reference</th>
                                <th>Student</th>
                                <th>Amount</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Receipt</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($payments)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">No payments found for this parent.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($payments as $pay): ?>
                                    <tr>
                                        <td class="ps-4 font-monospace small fw-bold"><?= e($pay['payment_reference']) ?></td>
                                        <td><?= e($pay['first_name'] . ' ' . $pay['last_name']) ?></td>
                                        <td class="fw-bold"><?= format_currency($pay['amount']) ?></td>
                                        <td class="small text-muted"><?= format_date($pay['created_at'], 'd M Y') ?></td>
                                        <td>
                                            <span class="badge bg-<?= $pay['payment_status'] === 'successful' ? 'success' : ($pay['payment_status'] === 'pending' ? 'warning' : 'danger') ?>">
                                                <?= ucfirst($pay['payment_status']) ?>
                                            </span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <?php if ($pay['payment_status'] === 'successful' && !empty($pay['receipt_number'])): ?>
                                                <a href="<?= BASE_URL ?>/parent/receipt.php?id=<?= $pay['id'] ?>" class="btn btn-sm btn-gold" target="_blank">
                                                    Receipt
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

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
