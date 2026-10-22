<?php
$page_title = "Student Registration Approvals";
$active_tab = "registrations";
require_once __DIR__ . '/../includes/admin_header.php';

$pdo = get_db_connection();
$cur_term = get_current_session_term();

// Fetch all active classes for assignment dropdown
$stmt_classes = $pdo->query("SELECT id, class_name, level_category FROM classes WHERE status = 'active' ORDER BY display_order ASC");
$classes = $stmt_classes->fetchAll();

// Handle Approval POST
if (isset($_POST['approve_student']) && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $student_id = (int)$_POST['student_id'];
    $assigned_class_id = (int)$_POST['assigned_class_id'];
    $student_id_number = trim($_POST['student_id_number']);
    $admission_date = !empty($_POST['admission_date']) ? $_POST['admission_date'] : date('Y-m-d');

    if (empty($student_id_number)) {
        $student_id_number = generate_student_id();
    }

    try {
        $pdo->beginTransaction();

        // 1. Update student record
        $stmt_app = $pdo->prepare("
            UPDATE students 
            SET registration_status = 'approved',
                enrolment_status = 'active',
                current_class_id = ?,
                student_id_number = ?,
                admission_date = ?,
                rejection_reason = NULL
            WHERE id = ?
        ");
        $stmt_app->execute([$assigned_class_id, $student_id_number, $admission_date, $student_id]);

        // 2. Fetch student and parent info
        $stmt_info = $pdo->prepare("
            SELECT s.first_name, s.last_name, s.parent_id, p.user_id, c.class_name
            FROM students s
            JOIN parents p ON s.parent_id = p.id
            JOIN classes c ON c.id = ?
            WHERE s.id = ?
        ");
        $stmt_info->execute([$assigned_class_id, $student_id]);
        $st_data = $stmt_info->fetch();

        // 3. Auto-generate student invoice for current session & term
        // Check if fee structures exist for this class
        $stmt_fees = $pdo->prepare("
            SELECT * FROM fee_structures 
            WHERE session_id = ? AND term_id = ? AND class_id = ?
        ");
        $stmt_fees->execute([$cur_term['session_id'], $cur_term['term_id'], $assigned_class_id]);
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
            $stmt_inv->execute([$inv_num, $student_id, $cur_term['session_id'], $cur_term['term_id'], $total_inv_amount, $total_inv_amount, $due_date]);
            $new_inv_id = (int)$pdo->lastInsertId();

            // Insert invoice items
            $stmt_item = $pdo->prepare("INSERT INTO student_invoice_items (invoice_id, fee_category_id, amount) VALUES (?, ?, ?)");
            foreach ($fee_items as $fi) {
                $stmt_item->execute([$new_inv_id, $fi['fee_category_id'], $fi['amount']]);
            }
        }

        $pdo->commit();

        // 4. Send notification to Parent
        if ($st_data) {
            send_broadcast_notification(
                (int)$_SESSION['user_id'],
                "Student Registration Approved: {$st_data['first_name']} {$st_data['last_name']}",
                "Assalamu Alaikum. We are pleased to inform you that the registration for {$st_data['first_name']} has been approved! Assigned Student ID: {$student_id_number}, Class: {$st_data['class_name']}. You may now view fee details and make payment in the portal.",
                "selected_parent",
                (int)$st_data['parent_id']
            );
        }

        log_audit('STUDENT_APPROVED', 'students', $student_id, "Approved registration for {$st_data['first_name']} (ID: {$student_id_number}, Class: {$assigned_class_id})");

        set_flash('success', "Student registration approved successfully! Assigned Student ID: {$student_id_number}.");
        header('Location: ' . BASE_URL . '/admin/registrations.php');
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        set_flash('danger', "Approval failed: " . $e->getMessage());
    }
}

// Handle Rejection POST
if (isset($_POST['reject_student']) && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $student_id = (int)$_POST['student_id'];
    $rejection_reason = trim($_POST['rejection_reason'] ?? 'Application does not meet the current admission requirements.');

    try {
        $stmt_rej = $pdo->prepare("
            UPDATE students 
            SET registration_status = 'rejected', rejection_reason = ? 
            WHERE id = ?
        ");
        $stmt_rej->execute([$rejection_reason, $student_id]);

        // Get info for notification
        $stmt_info = $pdo->prepare("
            SELECT s.first_name, s.last_name, s.parent_id, p.user_id 
            FROM students s 
            JOIN parents p ON s.parent_id = p.id 
            WHERE s.id = ?
        ");
        $stmt_info->execute([$student_id]);
        $st_data = $stmt_info->fetch();

        if ($st_data) {
            send_broadcast_notification(
                (int)$_SESSION['user_id'],
                "Registration Update: {$st_data['first_name']} {$st_data['last_name']}",
                "Regarding your application for {$st_data['first_name']}, it has not been approved at this time. Reason: {$rejection_reason}. Please contact the school office if you have inquiries.",
                "selected_parent",
                (int)$st_data['parent_id']
            );
        }

        log_audit('STUDENT_REJECTED', 'students', $student_id, "Rejected registration for {$st_data['first_name']}. Reason: {$rejection_reason}");

        set_flash('warning', "Registration application for student has been rejected.");
        header('Location: ' . BASE_URL . '/admin/registrations.php');
        exit;

    } catch (Exception $e) {
        set_flash('danger', "Rejection failed: " . $e->getMessage());
    }
}

// Filter Status
$filter = $_GET['status'] ?? 'pending';
$sql = "
    SELECT s.*, rc.class_name as requested_class_name, c.class_name as current_class_name,
           p.full_name as parent_name, u.phone as parent_phone, u.email as parent_email
    FROM students s
    JOIN parents p ON s.parent_id = p.id
    JOIN users u ON p.user_id = u.id
    LEFT JOIN classes rc ON s.requested_class_id = rc.id
    LEFT JOIN classes c ON s.current_class_id = c.id
";
if ($filter !== 'all') {
    $sql .= " WHERE s.registration_status = " . $pdo->quote($filter);
}
$sql .= " ORDER BY s.id DESC";

$applications = $pdo->query($sql)->fetchAll();
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold text-islamic mb-1">
            <i class="bi bi-person-check-fill text-gold me-2"></i> Student Registration Approvals
        </h3>
        <p class="text-muted small mb-0">Review student admission applications submitted by parents, assign classes, and issue Student IDs.</p>
    </div>
    <!-- Filter Tabs -->
    <div class="btn-group shadow-sm mt-3 mt-md-0" role="group">
        <a href="?status=pending" class="btn btn-sm <?= $filter === 'pending' ? 'btn-warning text-dark fw-bold' : 'btn-outline-secondary' ?>">
            <i class="bi bi-hourglass-split me-1"></i> Pending
        </a>
        <a href="?status=approved" class="btn btn-sm <?= $filter === 'approved' ? 'btn-success fw-bold' : 'btn-outline-secondary' ?>">
            <i class="bi bi-check-circle me-1"></i> Approved
        </a>
        <a href="?status=rejected" class="btn btn-sm <?= $filter === 'rejected' ? 'btn-danger fw-bold' : 'btn-outline-secondary' ?>">
            <i class="bi bi-x-circle me-1"></i> Rejected
        </a>
        <a href="?status=all" class="btn btn-sm <?= $filter === 'all' ? 'btn-islamic fw-bold' : 'btn-outline-secondary' ?>">
            All Applications
        </a>
    </div>
</div>

<div class="card card-modern">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h5 class="mb-0 text-islamic fw-bold">
            Applications List (<?= count($applications) ?>)
        </h5>
        <input type="text" class="form-control form-control-sm table-search-input" data-target-table="registrations_table" placeholder="Filter applications..." style="width: 220px;">
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="registrations_table">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Child Full Name</th>
                        <th>Parent / Contact</th>
                        <th>Gender / DOB</th>
                        <th>Requested Level</th>
                        <th>Previous Islamiyya</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($applications)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                No student applications found in this status.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($applications as $app): ?>
                            <?php 
                                $age = date_diff(date_create($app['date_of_birth']), date_create('today'))->y;
                            ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-islamic fs-6">
                                        <?= e($app['first_name'] . ' ' . $app['last_name'] . ' ' . ($app['other_name'] ?? '')) ?>
                                    </div>
                                    <small class="text-muted">
                                        <?= $app['student_id_number'] ? '<span class="badge bg-light text-dark border font-monospace">' . e($app['student_id_number']) . '</span>' : 'ID unassigned' ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="fw-semibold"><?= e($app['parent_name']) ?></div>
                                    <div class="small text-muted"><a href="tel:<?= e($app['parent_phone']) ?>" class="text-decoration-none"><?= e($app['parent_phone']) ?></a></div>
                                </td>
                                <td>
                                    <?= e($app['gender']) ?>
                                    <div class="small text-muted"><?= $age ?> yrs (<?= format_date($app['date_of_birth']) ?>)</div>
                                </td>
                                <td>
                                    <span class="badge bg-islamic-soft text-islamic">
                                        <?= e($app['requested_class_name'] ?? 'Not Specified') ?>
                                    </span>
                                    <?php if ($app['current_class_name']): ?>
                                        <div class="small text-success mt-1">Assigned: <?= e($app['current_class_name']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td style="max-width: 200px;">
                                    <small class="text-secondary"><?= e(mb_strimwidth($app['previous_islamiyya_education'] ?? 'None', 0, 60, "...")) ?></small>
                                </td>
                                <td>
                                    <?php if ($app['registration_status'] === 'approved'): ?>
                                        <span class="badge bg-success">Approved</span>
                                    <?php elseif ($app['registration_status'] === 'rejected'): ?>
                                        <span class="badge bg-danger" title="<?= e($app['rejection_reason']) ?>">Rejected</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <?php if ($app['registration_status'] === 'pending'): ?>
                                        <!-- Approve Modal Trigger -->
                                        <button type="button" class="btn btn-sm btn-success me-1" data-bs-toggle="modal" data-bs-target="#approveModal<?= $app['id'] ?>">
                                            <i class="bi bi-check-lg"></i> Approve
                                        </button>
                                        <!-- Reject Modal Trigger -->
                                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal<?= $app['id'] ?>">
                                            <i class="bi bi-x-lg"></i> Reject
                                        </button>
                                    <?php else: ?>
                                        <a href="<?= BASE_URL ?>/admin/student_view.php?id=<?= $app['id'] ?>" class="btn btn-sm btn-outline-islamic">
                                            View Student
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>

                            <?php if ($app['registration_status'] === 'pending'): ?>
                                <!-- Approve Modal -->
                                <div class="modal fade" id="approveModal<?= $app['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content text-start">
                                            <form method="POST" action="">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="student_id" value="<?= $app['id'] ?>">

                                                <div class="modal-header bg-islamic text-white">
                                                    <h5 class="modal-title fw-bold">
                                                        <i class="bi bi-person-check me-2 text-gold"></i> Approve Student Registration
                                                    </h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body p-4">
                                                    <p class="mb-3">
                                                        Approving registration for: <strong class="text-islamic"><?= e($app['first_name'] . ' ' . $app['last_name']) ?></strong><br>
                                                        Parent: <strong><?= e($app['parent_name']) ?></strong> (<?= e($app['parent_phone']) ?>)
                                                    </p>

                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">Assign Islamiyya Class <span class="text-danger">*</span></label>
                                                        <select name="assigned_class_id" class="form-select" required>
                                                            <?php foreach ($classes as $c): ?>
                                                                <option value="<?= $c['id'] ?>" <?= $app['requested_class_id'] == $c['id'] ? 'selected' : '' ?>>
                                                                    <?= e($c['class_name']) ?> (<?= e($c['level_category']) ?>)
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                        <small class="text-muted">Parent requested: <?= e($app['requested_class_name'] ?? 'None') ?></small>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">Assign Unique Student ID <span class="text-danger">*</span></label>
                                                        <input type="text" name="student_id_number" class="form-control font-monospace" value="<?= generate_student_id() ?>" required>
                                                        <small class="text-muted">Unique ID format: ISMS/YYYY/XXX</small>
                                                    </div>

                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">Admission Date</label>
                                                        <input type="date" name="admission_date" class="form-control" value="<?= date('Y-m-d') ?>">
                                                    </div>

                                                    <div class="alert alert-info py-2 small mb-0">
                                                        <i class="bi bi-info-circle me-1"></i> An initial term invoice will be auto-generated based on the approved class's fee schedule, and the parent will be notified.
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" name="approve_student" value="1" class="btn btn-islamic btn-sm px-4">
                                                        Confirm Approval
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <!-- Reject Modal -->
                                <div class="modal fade" id="rejectModal<?= $app['id'] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content text-start">
                                            <form method="POST" action="">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="student_id" value="<?= $app['id'] ?>">

                                                <div class="modal-header bg-danger text-white">
                                                    <h5 class="modal-title fw-bold">
                                                        <i class="bi bi-x-circle me-2"></i> Reject Application
                                                    </h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body p-4">
                                                    <p class="text-muted small">
                                                        Are you sure you want to reject the application for <strong><?= e($app['first_name'] . ' ' . $app['last_name']) ?></strong>?
                                                    </p>
                                                    <div class="mb-3">
                                                        <label class="form-label fw-semibold">Reason for Rejection <span class="text-danger">*</span></label>
                                                        <textarea name="rejection_reason" class="form-control" rows="3" required placeholder="Specify why the registration cannot be approved at this time..."></textarea>
                                                        <small class="text-muted">This message will be visible to the parent in their portal.</small>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" name="reject_student" value="1" class="btn btn-danger btn-sm">
                                                        Reject Application
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
