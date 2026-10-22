<?php
$page_title = "Manage Students";
$active_tab = "students";
require_once __DIR__ . '/../includes/admin_header.php';

$pdo = get_db_connection();
$cur_term = get_current_session_term();

// Fetch classes
$stmt_cls = $pdo->query("SELECT id, class_name, level_category FROM classes WHERE status = 'active' ORDER BY display_order ASC");
$classes = $stmt_cls->fetchAll();

// Fetch parents for manual student registration
$stmt_parents = $pdo->query("SELECT id, full_name, user_id FROM parents ORDER BY full_name ASC");
$parents_list = $stmt_parents->fetchAll();

// Handle Manual Student Registration by Admin
if (isset($_POST['manual_register_student']) && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $parent_id = (int)$_POST['parent_id'];
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $other_name = trim($_POST['other_name'] ?? '');
    $dob = trim($_POST['date_of_birth']);
    $gender = trim($_POST['gender']);
    $current_class_id = (int)$_POST['current_class_id'];
    $admission_date = !empty($_POST['admission_date']) ? $_POST['admission_date'] : date('Y-m-d');
    $student_id_number = trim($_POST['student_id_number'] ?? '');
    $previous_edu = trim($_POST['previous_islamiyya_education'] ?? '');

    if (empty($student_id_number)) {
        $student_id_number = generate_student_id();
    }

    try {
        $pdo->beginTransaction();

        $stmt_ins = $pdo->prepare("
            INSERT INTO students (
                parent_id, student_id_number, first_name, last_name, other_name,
                date_of_birth, gender, current_class_id, requested_class_id,
                registration_status, enrolment_status, admission_date, previous_islamiyya_education
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', 'active', ?, ?)
        ");
        $stmt_ins->execute([
            $parent_id, $student_id_number, $first_name, $last_name, $other_name,
            $dob, $gender, $current_class_id, $current_class_id,
            $admission_date, $previous_edu
        ]);
        $new_student_id = (int)$pdo->lastInsertId();

        // Generate initial term invoice
        $stmt_fees = $pdo->prepare("SELECT * FROM fee_structures WHERE session_id = ? AND term_id = ? AND class_id = ?");
        $stmt_fees->execute([$cur_term['session_id'], $cur_term['term_id'], $current_class_id]);
        $fee_items = $stmt_fees->fetchAll();

        $total_inv_amount = 0.00;
        foreach ($fee_items as $fi) {
            $total_inv_amount += (float)$fi['amount'];
        }

        if ($total_inv_amount > 0) {
            $inv_num = generate_invoice_number();
            $due_date = date('Y-m-d', strtotime('+30 days'));

            $stmt_inv = $pdo->prepare("
                INSERT INTO student_invoices (invoice_number, student_id, session_id, term_id, total_amount, amount_paid, balance, status, due_date)
                VALUES (?, ?, ?, ?, ?, 0.00, ?, 'unpaid', ?)
            ");
            $stmt_inv->execute([$inv_num, $new_student_id, $cur_term['session_id'], $cur_term['term_id'], $total_inv_amount, $total_inv_amount, $due_date]);
            $inv_id = (int)$pdo->lastInsertId();

            $stmt_item = $pdo->prepare("INSERT INTO student_invoice_items (invoice_id, fee_category_id, amount) VALUES (?, ?, ?)");
            foreach ($fee_items as $fi) {
                $stmt_item->execute([$inv_id, $fi['fee_category_id'], $fi['amount']]);
            }
        }

        $pdo->commit();

        log_audit('STUDENT_MANUAL_REGISTRATION', 'students', $new_student_id, "Admin manually registered student {$first_name} {$last_name} (ID: {$student_id_number})");
        set_flash('success', "Student {$first_name} {$last_name} registered successfully with ID: {$student_id_number}");
        header('Location: ' . BASE_URL . '/admin/students.php');
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        set_flash('danger', "Registration error: " . $e->getMessage());
    }
}

// Search & Filter
$search = trim($_GET['search'] ?? '');
$class_filter = (int)($_GET['class_id'] ?? 0);
$status_filter = $_GET['enrolment_status'] ?? 'active';

$sql = "
    SELECT 
        s.*, c.class_name, c.level_category,
        pt.full_name as parent_name, u.phone as parent_phone,
        COALESCE(SUM(inv.balance), 0) as fee_balance
    FROM students s
    JOIN parents pt ON s.parent_id = pt.id
    JOIN users u ON pt.user_id = u.id
    LEFT JOIN classes c ON s.current_class_id = c.id
    LEFT JOIN student_invoices inv ON s.id = inv.student_id
    WHERE s.registration_status = 'approved'
";
$params = [];
if (!empty($search)) {
    $sql .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_id_number LIKE ? OR pt.full_name LIKE ?)";
    $term = "%{$search}%";
    $params = [$term, $term, $term, $term];
}
if ($class_filter > 0) {
    $sql .= " AND s.current_class_id = ?";
    $params[] = $class_filter;
}
if (!empty($status_filter) && $status_filter !== 'all') {
    $sql .= " AND s.enrolment_status = ?";
    $params[] = $status_filter;
}
$sql .= " GROUP BY s.id ORDER BY s.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold text-islamic mb-1">
            <i class="bi bi-mortarboard-fill text-gold me-2"></i> Manage Students
        </h3>
        <p class="text-muted small mb-0">Complete roster of enrolled Islamiyya students, class assignments, and fee accounts.</p>
    </div>
    <div class="mt-3 mt-md-0 d-flex gap-2">
        <button type="button" class="btn btn-gold shadow-sm btn-sm" data-bs-toggle="modal" data-bs-target="#manualRegisterModal">
            <i class="bi bi-person-plus-fill me-1"></i> Register Student Manually
        </button>
        <a href="<?= BASE_URL ?>/admin/reports.php" class="btn btn-outline-islamic btn-sm">
            <i class="bi bi-download me-1"></i> Export Roster
        </a>
    </div>
</div>

<!-- Search & Filter Card -->
<div class="card card-modern p-3 mb-4">
    <form method="GET" action="" class="row g-2 align-items-center">
        <div class="col-md-4">
            <div class="input-group">
                <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                <input type="text" name="search" class="form-control" value="<?= e($search) ?>" placeholder="Search name, student ID, parent...">
            </div>
        </div>
        <div class="col-md-3">
            <select name="class_id" class="form-select">
                <option value="0">All Islamiyya Classes</option>
                <?php foreach ($classes as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $class_filter == $c['id'] ? 'selected' : '' ?>>
                        <?= e($c['class_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <select name="enrolment_status" class="form-select">
                <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Enrolment Statuses</option>
                <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active Students</option>
                <option value="graduated" <?= $status_filter === 'graduated' ? 'selected' : '' ?>>Graduated</option>
                <option value="withdrawn" <?= $status_filter === 'withdrawn' ? 'selected' : '' ?>>Withdrawn</option>
                <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-islamic w-100">Filter</button>
        </div>
    </form>
</div>

<!-- Students Table -->
<div class="card card-modern">
    <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center py-3 gap-2">
        <h5 class="mb-0 text-islamic fw-bold">
            <i class="bi bi-people-fill text-gold me-2"></i> Enrolled Students (<?= count($students) ?>)
        </h5>
        <div class="table-search-wrap" style="width: 250px;">
            <input type="text" class="form-control form-control-sm table-search-input pe-5" data-target-table="students_table" placeholder="Instant filter table...">
            <span class="table-search-results"></span>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="students_table">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Student ID / Name</th>
                        <th>Class / Level</th>
                        <th>Gender / Age</th>
                        <th>Parent & Phone</th>
                        <th>Fee Balance (₦)</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">No students matching the criteria.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($students as $st): ?>
                            <?php $age = date_diff(date_create($st['date_of_birth']), date_create('today'))->y; ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-islamic fs-6">
                                        <?= e($st['first_name'] . ' ' . $st['last_name'] . ' ' . ($st['other_name'] ?? '')) ?>
                                    </div>
                                    <div class="d-flex align-items-center mt-1">
                                        <span class="badge bg-light text-dark border font-monospace"><?= e($st['student_id_number']) ?></span>
                                        <button type="button" class="copy-btn py-0 px-2 ms-1" data-copy="<?= e($st['student_id_number']) ?>" title="Copy Student ID">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-islamic-soft text-islamic fw-semibold">
                                        <?= e($st['class_name'] ?? 'Not Assigned') ?>
                                    </span>
                                </td>
                                <td>
                                    <?= e($st['gender']) ?>
                                    <div class="small text-muted"><?= $age ?> yrs (<?= format_date($st['date_of_birth']) ?>)</div>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?= e($st['parent_name']) ?></div>
                                    <div class="d-flex align-items-center">
                                        <small class="text-muted"><a href="tel:<?= e($st['parent_phone']) ?>" class="text-decoration-none"><?= e($st['parent_phone']) ?></a></small>
                                        <button type="button" class="copy-btn py-0 px-1 ms-1" data-copy="<?= e($st['parent_phone']) ?>" title="Copy Phone">
                                            <i class="bi bi-telephone"></i>
                                        </button>
                                    </div>
                                </td>
                                <td class="fw-bold fs-6 <?= $st['fee_balance'] > 0 ? 'text-danger' : 'text-success' ?>">
                                    <?= format_currency($st['fee_balance']) ?>
                                </td>
                                <td>
                                    <?php if ($st['enrolment_status'] === 'active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php elseif ($st['enrolment_status'] === 'graduated'): ?>
                                        <span class="badge bg-primary">Graduated</span>
                                    <?php elseif ($st['enrolment_status'] === 'withdrawn'): ?>
                                        <span class="badge bg-warning text-dark">Withdrawn</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <a href="<?= BASE_URL ?>/admin/student_view.php?id=<?= $st['id'] ?>" class="btn btn-sm btn-outline-islamic">
                                        <i class="bi bi-eye me-1"></i> Profile & Fees
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

<!-- Modal: Manual Student Registration -->
<div class="modal fade" id="manualRegisterModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content text-start">
            <form method="POST" action="">
                <?= csrf_field() ?>
                <div class="modal-header bg-islamic text-white">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-person-plus-fill me-2 text-gold"></i> Manual Student Registration
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Select Parent / Guardian <span class="text-danger">*</span></label>
                            <select name="parent_id" class="form-select" required>
                                <option value="">-- Choose Registered Parent --</option>
                                <?php foreach ($parents_list as $p_item): ?>
                                    <option value="<?= $p_item['id'] ?>"><?= e($p_item['full_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" class="form-control" required placeholder="e.g. Usman">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Last Name (Surname) <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" class="form-control" required placeholder="e.g. Bello">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Other Name</label>
                            <input type="text" name="other_name" class="form-control" placeholder="e.g. Al-Mansoor">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Date of Birth <span class="text-danger">*</span></label>
                            <input type="date" name="date_of_birth" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Gender <span class="text-danger">*</span></label>
                            <select name="gender" class="form-select" required>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Assign Class <span class="text-danger">*</span></label>
                            <select name="current_class_id" class="form-select" required>
                                <?php foreach ($classes as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= e($c['class_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Assign Student ID</label>
                            <input type="text" name="student_id_number" class="form-control font-monospace" value="<?= generate_student_id() ?>" placeholder="Leave blank to auto-generate">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Admission Date</label>
                            <input type="date" name="admission_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Previous Islamiyya Background</label>
                            <textarea name="previous_islamiyya_education" class="form-control" rows="2" placeholder="Prior madrasah or memorization background..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="manual_register_student" value="1" class="btn btn-islamic btn-sm px-4">
                        Register Student & Issue Invoice
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
