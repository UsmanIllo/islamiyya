<?php
$page_title = "Student Profile & Fees";
$active_tab = "students";
require_once __DIR__ . '/../includes/admin_header.php';

$pdo = get_db_connection();
$student_id = (int)($_GET['id'] ?? 0);
$cur_term = get_current_session_term();

// Fetch student
$stmt = $pdo->prepare("
    SELECT s.*, c.class_name as current_class_name, c.level_category,
           pt.full_name as parent_name, pt.id as parent_id, u.phone as parent_phone, u.email as parent_email,
           pt.residential_address as parent_address
    FROM students s
    JOIN parents pt ON s.parent_id = pt.id
    JOIN users u ON pt.user_id = u.id
    LEFT JOIN classes c ON s.current_class_id = c.id
    WHERE s.id = ?
");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    set_flash('danger', 'Student record not found.');
    header('Location: ' . BASE_URL . '/admin/students.php');
    exit;
}

// Fetch all classes for promotion / reassignment
$classes = $pdo->query("SELECT id, class_name, level_category FROM classes WHERE status = 'active' ORDER BY display_order ASC")->fetchAll();

// Handle Edit Student Information POST
if (isset($_POST['update_student']) && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $other_name = trim($_POST['other_name'] ?? '');
    $student_id_number = trim($_POST['student_id_number']);
    $dob = trim($_POST['date_of_birth']);
    $gender = trim($_POST['gender']);
    $current_class_id = (int)$_POST['current_class_id'];
    $enrolment_status = $_POST['enrolment_status'];
    $admission_date = $_POST['admission_date'];
    $previous_edu = trim($_POST['previous_islamiyya_education']);

    try {
        $stmt_up = $pdo->prepare("
            UPDATE students 
            SET first_name = ?, last_name = ?, other_name = ?, student_id_number = ?,
                date_of_birth = ?, gender = ?, current_class_id = ?, enrolment_status = ?,
                admission_date = ?, previous_islamiyya_education = ?
            WHERE id = ?
        ");
        $stmt_up->execute([
            $first_name, $last_name, $other_name, $student_id_number,
            $dob, $gender, $current_class_id, $enrolment_status,
            $admission_date, $previous_edu, $student_id
        ]);

        log_audit('STUDENT_UPDATED', 'students', $student_id, "Admin updated student info for {$first_name} {$last_name}");
        set_flash('success', "Student record updated successfully.");
        header('Location: ' . BASE_URL . '/admin/student_view.php?id=' . $student_id);
        exit;
    } catch (Exception $e) {
        set_flash('danger', "Update failed: " . $e->getMessage());
    }
}

// Handle Student Promotion POST
if (isset($_POST['promote_student']) && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $new_class_id = (int)$_POST['new_class_id'];
    try {
        $stmt_promo = $pdo->prepare("UPDATE students SET current_class_id = ? WHERE id = ?");
        $stmt_promo->execute([$new_class_id, $student_id]);

        // Get class name
        $stmt_c = $pdo->prepare("SELECT class_name FROM classes WHERE id = ?");
        $stmt_c->execute([$new_class_id]);
        $cname = $stmt_c->fetchColumn();

        // Notify Parent
        send_broadcast_notification(
            (int)$_SESSION['user_id'],
            "Student Promotion: {$student['first_name']} {$student['last_name']}",
            "Congratulations! {$student['first_name']} has been promoted to {$cname}. May Allah grant them continuous success and beneficial Islamic knowledge.",
            "selected_parent",
            (int)$student['parent_id']
        );

        log_audit('STUDENT_PROMOTED', 'students', $student_id, "Promoted {$student['first_name']} to class ID {$new_class_id}");
        set_flash('success', "Student successfully promoted to {$cname}!");
        header('Location: ' . BASE_URL . '/admin/student_view.php?id=' . $student_id);
        exit;
    } catch (Exception $e) {
        set_flash('danger', "Promotion failed: " . $e->getMessage());
    }
}

// Invoices for this student
$stmt_inv = $pdo->prepare("
    SELECT inv.*, sess.session_name, t.term_name
    FROM student_invoices inv
    JOIN academic_sessions sess ON inv.session_id = sess.id
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

$age = date_diff(date_create($student['date_of_birth']), date_create('today'))->y;
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <a href="<?= BASE_URL ?>/admin/students.php" class="text-decoration-none small text-muted mb-1 d-inline-block">
            <i class="bi bi-arrow-left"></i> Back to Student Roster
        </a>
        <h3 class="fw-bold text-islamic mb-0">
            <?= e($student['first_name'] . ' ' . $student['last_name'] . ' ' . ($student['other_name'] ?? '')) ?>
        </h3>
    </div>
    <div class="mt-3 mt-md-0 d-flex gap-2">
        <button type="button" class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#promoteModal">
            <i class="bi bi-arrow-up-circle me-1"></i> Promote to Next Class
        </button>
    </div>
</div>

<div class="row g-4">
    <!-- Student Profile & Edit Form -->
    <div class="col-lg-5">
        <div class="card card-modern p-4 mb-4">
            <div class="d-flex justify-content-between align-items-start mb-3 border-bottom pb-3">
                <div>
                    <h5 class="fw-bold text-islamic mb-1">Student Record</h5>
                    <span class="badge bg-gold font-monospace fs-6"><?= e($student['student_id_number']) ?></span>
                </div>
                <span class="badge bg-<?= $student['enrolment_status'] === 'active' ? 'success' : 'secondary' ?> fs-6">
                    <?= ucfirst($student['enrolment_status']) ?>
                </span>
            </div>

            <form method="POST" action="">
                <?= csrf_field() ?>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">First Name</label>
                        <input type="text" name="first_name" class="form-control form-control-sm" value="<?= e($student['first_name']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Last Name</label>
                        <input type="text" name="last_name" class="form-control form-control-sm" value="<?= e($student['last_name']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Other Name</label>
                        <input type="text" name="other_name" class="form-control form-control-sm" value="<?= e($student['other_name']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Student ID No.</label>
                        <input type="text" name="student_id_number" class="form-control form-control-sm font-monospace" value="<?= e($student['student_id_number']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Date of Birth</label>
                        <input type="date" name="date_of_birth" class="form-control form-control-sm" value="<?= e($student['date_of_birth']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Gender</label>
                        <select name="gender" class="form-select form-select-sm" required>
                            <option value="Male" <?= $student['gender'] === 'Male' ? 'selected' : '' ?>>Male</option>
                            <option value="Female" <?= $student['gender'] === 'Female' ? 'selected' : '' ?>>Female</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Current Class</label>
                        <select name="current_class_id" class="form-select form-select-sm" required>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= $student['current_class_id'] == $c['id'] ? 'selected' : '' ?>>
                                    <?= e($c['class_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Enrolment Status</label>
                        <select name="enrolment_status" class="form-select form-select-sm" required>
                            <option value="active" <?= $student['enrolment_status'] === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="graduated" <?= $student['enrolment_status'] === 'graduated' ? 'selected' : '' ?>>Graduated</option>
                            <option value="withdrawn" <?= $student['enrolment_status'] === 'withdrawn' ? 'selected' : '' ?>>Withdrawn</option>
                            <option value="inactive" <?= $student['enrolment_status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label small fw-semibold">Admission Date</label>
                        <input type="date" name="admission_date" class="form-control form-control-sm" value="<?= e($student['admission_date']) ?>">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label small fw-semibold">Previous Quranic / Islamiyya Background</label>
                        <textarea name="previous_islamiyya_education" class="form-control form-control-sm" rows="2"><?= e($student['previous_islamiyya_education']) ?></textarea>
                    </div>
                </div>

                <div class="p-3 bg-light rounded-3 mb-3 small">
                    <span class="text-muted d-block">Parent / Guardian:</span>
                    <strong class="text-islamic fs-6"><?= e($student['parent_name']) ?></strong>
                    <div class="text-muted">Tel: <?= e($student['parent_phone']) ?> | <a href="<?= BASE_URL ?>/admin/parent_view.php?id=<?= $student['parent_id'] ?>">View Parent</a></div>
                </div>

                <button type="submit" name="update_student" value="1" class="btn btn-islamic btn-sm w-100">
                    <i class="bi bi-save me-1"></i> Update Student Information
                </button>
            </form>
        </div>
    </div>

    <!-- Invoices & Fee Ledger -->
    <div class="col-lg-7">
        <!-- Invoices -->
        <div class="card card-modern mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <h5 class="mb-0 text-islamic fw-bold">
                    <i class="bi bi-receipt text-gold me-2"></i> Fee Invoices
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Invoice #</th>
                                <th>Term</th>
                                <th>Assessed</th>
                                <th>Paid</th>
                                <th>Balance (₦)</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($invoices)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">No invoices generated for this student yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($invoices as $inv): ?>
                                    <tr>
                                        <td class="ps-4 font-monospace fw-bold small"><?= e($inv['invoice_number']) ?></td>
                                        <td><?= e($inv['session_name']) ?><br><small class="text-muted"><?= e($inv['term_name']) ?></small></td>
                                        <td><?= format_currency($inv['total_amount']) ?></td>
                                        <td class="text-success"><?= format_currency($inv['amount_paid']) ?></td>
                                        <td class="fw-bold <?= $inv['balance'] > 0 ? 'text-danger' : 'text-muted' ?>">
                                            <?= format_currency($inv['balance']) ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $inv['status'] === 'paid' ? 'success' : ($inv['status'] === 'partially_paid' ? 'warning text-dark' : 'danger') ?>">
                                                <?= ucfirst(str_replace('_', ' ', $inv['status'])) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Payments -->
        <div class="card card-modern">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <h5 class="mb-0 text-islamic fw-bold">
                    <i class="bi bi-clock-history text-gold me-2"></i> Payments Received
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
                                    <td colspan="6" class="text-center py-4 text-muted">No payments recorded for this student yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($payments as $pay): ?>
                                    <tr>
                                        <td class="ps-4 font-monospace small fw-bold"><?= e($pay['payment_reference']) ?></td>
                                        <td class="fw-bold"><?= format_currency($pay['amount']) ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= e(str_replace('_', ' ', strtoupper($pay['payment_method']))) ?></span></td>
                                        <td class="small text-muted"><?= format_date($pay['created_at']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $pay['payment_status'] === 'successful' ? 'success' : 'warning' ?>">
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

<!-- Modal Promote Student -->
<div class="modal fade" id="promoteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content text-start">
            <form method="POST" action="">
                <?= csrf_field() ?>
                <div class="modal-header bg-gold text-white">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-arrow-up-circle-fill me-2"></i> Promote Student
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="small text-muted mb-3">
                        Promote <strong><?= e($student['first_name'] . ' ' . $student['last_name']) ?></strong> from 
                        <span class="badge bg-islamic-soft text-islamic"><?= e($student['current_class_name']) ?></span> to a higher Islamiyya level.
                    </p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Promote to Class <span class="text-danger">*</span></label>
                        <select name="new_class_id" class="form-select" required>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= $student['current_class_id'] == $c['id'] ? 'disabled' : '' ?>>
                                    <?= e($c['class_name']) ?> (<?= e($c['level_category']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="promote_student" value="1" class="btn btn-gold btn-sm px-4">
                        Confirm Promotion
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
