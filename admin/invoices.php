<?php
$page_title = "Student Invoices";
$active_tab = "invoices";
require_once __DIR__ . '/../includes/admin_header.php';

$pdo = get_db_connection();
$cur_term = get_current_session_term();

// Handle Bulk Invoice Generation for Active Students POST
if (isset($_POST['generate_bulk_invoices']) && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $session_id = (int)$_POST['session_id'];
    $term_id = (int)$_POST['term_id'];

    try {
        $pdo->beginTransaction();

        // Fetch all active students
        $stmt_st = $pdo->query("SELECT id, current_class_id FROM students WHERE enrolment_status = 'active' AND registration_status = 'approved' AND current_class_id IS NOT NULL");
        $students = $stmt_st->fetchAll();

        $generated_count = 0;
        foreach ($students as $st) {
            // Check if student already has an invoice for this session & term
            $chk = $pdo->prepare("SELECT id FROM student_invoices WHERE student_id = ? AND session_id = ? AND term_id = ?");
            $chk->execute([$st['id'], $session_id, $term_id]);
            if ($chk->rowCount() > 0) {
                continue; // Skip already invoiced
            }

            // Fetch fees for student's class
            $stmt_f = $pdo->prepare("SELECT * FROM fee_structures WHERE session_id = ? AND term_id = ? AND class_id = ?");
            $stmt_f->execute([$session_id, $term_id, $st['current_class_id']]);
            $fees = $stmt_f->fetchAll();

            $total_amount = 0.00;
            foreach ($fees as $f) {
                $total_amount += (float)$f['amount'];
            }

            if ($total_amount > 0) {
                $inv_num = generate_invoice_number();
                $due_date = date('Y-m-d', strtotime('+30 days'));

                $stmt_ins = $pdo->prepare("
                    INSERT INTO student_invoices (invoice_number, student_id, session_id, term_id, total_amount, amount_paid, balance, status, due_date)
                    VALUES (?, ?, ?, ?, ?, 0.00, ?, 'unpaid', ?)
                ");
                $stmt_ins->execute([$inv_num, $st['id'], $session_id, $term_id, $total_amount, $total_amount, $due_date]);
                $inv_id = (int)$pdo->lastInsertId();

                $stmt_item = $pdo->prepare("INSERT INTO student_invoice_items (invoice_id, fee_category_id, amount) VALUES (?, ?, ?)");
                foreach ($fees as $f) {
                    $stmt_item->execute([$inv_id, $f['fee_category_id'], $f['amount']]);
                }
                $generated_count++;
            }
        }

        $pdo->commit();
        log_audit('BULK_INVOICES_GENERATED', 'student_invoices', null, "Generated {$generated_count} invoices for session {$session_id}, term {$term_id}");
        set_flash('success', "Bulk invoicing complete! {$generated_count} new invoice(s) generated successfully.");
        header('Location: ' . BASE_URL . '/admin/invoices.php');
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        set_flash('danger', "Invoice generation failed: " . $e->getMessage());
    }
}

// Filter Invoices
$status_filter = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$sql = "
    SELECT 
        inv.*, 
        s.first_name, s.last_name, s.student_id_number,
        c.class_name,
        pt.full_name as parent_name, u.phone as parent_phone,
        sess.session_name, t.term_name
    FROM student_invoices inv
    JOIN students s ON inv.student_id = s.id
    LEFT JOIN classes c ON s.current_class_id = c.id
    JOIN parents pt ON s.parent_id = pt.id
    JOIN users u ON pt.user_id = u.id
    JOIN academic_sessions sess ON inv.session_id = sess.id
    JOIN academic_terms t ON inv.term_id = t.id
    WHERE 1=1
";
$params = [];
if ($status_filter !== 'all') {
    $sql .= " AND inv.status = ?";
    $params[] = $status_filter;
}
if (!empty($search)) {
    $sql .= " AND (inv.invoice_number LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_id_number LIKE ? OR pt.full_name LIKE ?)";
    $term = "%{$search}%";
    $params = array_merge($params, [$term, $term, $term, $term, $term]);
}
$sql .= " ORDER BY inv.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll();

// Metrics
$total_invoiced = 0;
$total_paid = 0;
$total_balance = 0;
foreach ($invoices as $i) {
    $total_invoiced += (float)$i['total_amount'];
    $total_paid += (float)$i['amount_paid'];
    $total_balance += (float)$i['balance'];
}
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold text-islamic mb-1">
            <i class="bi bi-receipt text-gold me-2"></i> Student Fee Invoices
        </h3>
        <p class="text-muted small mb-0">Monitor student fee accounts, assess charges, and generate invoices in bulk.</p>
    </div>
    <div class="mt-3 mt-md-0 d-flex gap-2">
        <form method="POST" action="" onsubmit="return confirmAction('Generate term invoices for all currently enrolled students?')">
            <?= csrf_field() ?>
            <input type="hidden" name="session_id" value="<?= $cur_term['session_id'] ?>">
            <input type="hidden" name="term_id" value="<?= $cur_term['term_id'] ?>">
            <button type="submit" name="generate_bulk_invoices" value="1" class="btn btn-gold btn-sm shadow-sm">
                <i class="bi bi-lightning-charge-fill me-1"></i> Generate Term Invoices for All Students
            </button>
        </form>
    </div>
</div>

<!-- Financial Summary Row -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card stat-card p-3">
            <span class="text-muted small fw-bold text-uppercase">Total Invoiced</span>
            <h4 class="fw-bold text-dark mt-1 mb-0"><?= format_currency($total_invoiced) ?></h4>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card gold-border p-3">
            <span class="text-muted small fw-bold text-uppercase">Total Amount Paid</span>
            <h4 class="fw-bold text-success mt-1 mb-0"><?= format_currency($total_paid) ?></h4>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card danger-border p-3">
            <span class="text-muted small fw-bold text-uppercase">Outstanding Debt</span>
            <h4 class="fw-bold text-danger mt-1 mb-0"><?= format_currency($total_balance) ?></h4>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card card-modern p-3 mb-4">
    <form method="GET" action="" class="row g-2 align-items-center">
        <div class="col-md-5">
            <div class="input-group">
                <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                <input type="text" name="search" class="form-control" value="<?= e($search) ?>" placeholder="Search invoice #, student name, ID, parent...">
            </div>
        </div>
        <div class="col-md-4">
            <select name="status" class="form-select">
                <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All Invoices</option>
                <option value="unpaid" <?= $status_filter === 'unpaid' ? 'selected' : '' ?>>Unpaid Only</option>
                <option value="partially_paid" <?= $status_filter === 'partially_paid' ? 'selected' : '' ?>>Partially Paid</option>
                <option value="paid" <?= $status_filter === 'paid' ? 'selected' : '' ?>>Paid in Full</option>
                <option value="overdue" <?= $status_filter === 'overdue' ? 'selected' : '' ?>>Overdue</option>
            </select>
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-islamic w-100">Filter Invoices</button>
        </div>
    </form>
</div>

<!-- Invoices Table -->
<div class="card card-modern">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h5 class="mb-0 text-islamic fw-bold">Invoice Records (<?= count($invoices) ?>)</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Invoice #</th>
                        <th>Student & Class</th>
                        <th>Parent & Phone</th>
                        <th>Total Fee (₦)</th>
                        <th>Paid (₦)</th>
                        <th>Balance (₦)</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($invoices)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">No invoices found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($invoices as $inv): ?>
                            <tr>
                                <td class="ps-4 font-monospace fw-bold small text-islamic"><?= e($inv['invoice_number']) ?></td>
                                <td>
                                    <div class="fw-bold"><?= e($inv['first_name'] . ' ' . $inv['last_name']) ?></div>
                                    <small class="text-muted"><?= e($inv['class_name'] ?? 'Class') ?> &bull; <?= e($inv['student_id_number'] ?? 'Pending') ?></small>
                                </td>
                                <td>
                                    <div><?= e($inv['parent_name']) ?></div>
                                    <small class="text-muted"><?= e($inv['parent_phone']) ?></small>
                                </td>
                                <td class="fw-bold"><?= format_currency($inv['total_amount']) ?></td>
                                <td class="text-success"><?= format_currency($inv['amount_paid']) ?></td>
                                <td class="fw-bold fs-6 <?= $inv['balance'] > 0 ? 'text-danger' : 'text-success' ?>">
                                    <?= format_currency($inv['balance']) ?>
                                </td>
                                <td class="small text-muted"><?= format_date($inv['due_date']) ?></td>
                                <td>
                                    <span class="badge bg-<?= $inv['status'] === 'paid' ? 'success' : ($inv['status'] === 'partially_paid' ? 'warning text-dark' : 'danger') ?>">
                                        <?= ucfirst(str_replace('_', ' ', $inv['status'])) ?>
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <a href="<?= BASE_URL ?>/admin/student_view.php?id=<?= $inv['student_id'] ?>" class="btn btn-sm btn-outline-islamic">
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

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
