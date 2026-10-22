<?php
$page_title = "Records & Reports";
$active_tab = "reports";
require_once __DIR__ . '/../includes/admin_header.php';

$pdo = get_db_connection();
$cur_term = get_current_session_term();

$report_type = $_GET['type'] ?? 'enrolment';

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=isms_' . $report_type . '_report_' . date('Y-m-d') . '.csv');
    $out = fopen('php://output', 'w');

    if ($report_type === 'debtors') {
        fputcsv($out, ['Student ID', 'Student Name', 'Class', 'Parent Name', 'Parent Phone', 'Total Assessed (NGN)', 'Paid (NGN)', 'Debt Balance (NGN)', 'Due Date']);
        $stmt = $pdo->query("
            SELECT s.student_id_number, CONCAT(s.first_name, ' ', s.last_name) as student_name, c.class_name,
                   pt.full_name as parent_name, u.phone as parent_phone, inv.total_amount, inv.amount_paid, inv.balance, inv.due_date
            FROM student_invoices inv
            JOIN students s ON inv.student_id = s.id
            LEFT JOIN classes c ON s.current_class_id = c.id
            JOIN parents pt ON s.parent_id = pt.id
            JOIN users u ON pt.user_id = u.id
            WHERE inv.balance > 0
            ORDER BY inv.balance DESC
        ");
        while ($r = $stmt->fetch()) {
            fputcsv($out, [$r['student_id_number'] ?? 'N/A', $r['student_name'], $r['class_name'] ?? 'N/A', $r['parent_name'], $r['parent_phone'], $r['total_amount'], $r['amount_paid'], $r['balance'], $r['due_date']]);
        }
    } elseif ($report_type === 'revenue') {
        fputcsv($out, ['Payment Reference', 'Receipt Number', 'Student Name', 'Parent Name', 'Amount (NGN)', 'Method', 'Date Verified']);
        $stmt = $pdo->query("
            SELECT p.payment_reference, r.receipt_number, CONCAT(s.first_name, ' ', s.last_name) as student_name, pt.full_name as parent_name, p.amount, p.payment_method, p.verified_at
            FROM payments p
            JOIN students s ON p.student_id = s.id
            JOIN parents pt ON p.parent_id = pt.id
            LEFT JOIN receipts r ON p.id = r.payment_id
            WHERE p.payment_status = 'successful'
            ORDER BY p.id DESC
        ");
        while ($r = $stmt->fetch()) {
            fputcsv($out, [$r['payment_reference'], $r['receipt_number'] ?? 'N/A', $r['student_name'], $r['parent_name'], $r['amount'], $r['payment_method'], $r['verified_at']]);
        }
    } else {
        // Enrolment
        fputcsv($out, ['Student ID', 'First Name', 'Last Name', 'Gender', 'Class', 'Category', 'Parent Name', 'Parent Phone', 'Admission Date', 'Status']);
        $stmt = $pdo->query("
            SELECT s.student_id_number, s.first_name, s.last_name, s.gender, c.class_name, c.level_category, pt.full_name as parent_name, u.phone as parent_phone, s.admission_date, s.enrolment_status
            FROM students s
            LEFT JOIN classes c ON s.current_class_id = c.id
            JOIN parents pt ON s.parent_id = pt.id
            JOIN users u ON pt.user_id = u.id
            WHERE s.registration_status = 'approved'
            ORDER BY c.display_order ASC, s.last_name ASC
        ");
        while ($r = $stmt->fetch()) {
            fputcsv($out, [$r['student_id_number'] ?? 'N/A', $r['first_name'], $r['last_name'], $r['gender'], $r['class_name'] ?? 'N/A', $r['level_category'] ?? 'N/A', $r['parent_name'], $r['parent_phone'], $r['admission_date'], $r['enrolment_status']]);
        }
    }
    fclose($out);
    exit;
}

// 1. Enrolment Stats by Class & Gender
$class_enrolment = $pdo->query("
    SELECT 
        c.class_name, c.level_category,
        COUNT(s.id) as total_students,
        SUM(CASE WHEN s.gender = 'Male' THEN 1 ELSE 0 END) as male_count,
        SUM(CASE WHEN s.gender = 'Female' THEN 1 ELSE 0 END) as female_count
    FROM classes c
    LEFT JOIN students s ON s.current_class_id = c.id AND s.enrolment_status = 'active'
    WHERE c.status = 'active'
    GROUP BY c.id
    ORDER BY c.display_order ASC
")->fetchAll();

// 2. Financial Summary
$fin_summary = $pdo->query("
    SELECT 
        COALESCE(SUM(inv.total_amount), 0) as total_assessed,
        COALESCE(SUM(inv.amount_paid), 0) as total_collected,
        COALESCE(SUM(inv.balance), 0) as total_debt
    FROM student_invoices inv
")->fetch();

// 3. Revenue by Payment Method
$rev_methods = $pdo->query("
    SELECT payment_method, COUNT(id) as tx_count, COALESCE(SUM(amount), 0) as total_amount
    FROM payments
    WHERE payment_status = 'successful'
    GROUP BY payment_method
")->fetchAll();
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold text-islamic mb-1">
            <i class="bi bi-file-earmark-bar-graph-fill text-gold me-2"></i> Records & Analytical Reports
        </h3>
        <p class="text-muted small mb-0">Exportable enrolment statistics, financial summaries in Nigerian Naira (₦), and debt reports.</p>
    </div>
    <!-- Report Switcher & Export -->
    <div class="mt-3 mt-md-0 d-flex gap-2">
        <div class="btn-group shadow-sm" role="group">
            <a href="?type=enrolment" class="btn btn-sm <?= $report_type === 'enrolment' ? 'btn-islamic fw-bold' : 'btn-outline-secondary' ?>">
                <i class="bi bi-people me-1"></i> Enrolment
            </a>
            <a href="?type=revenue" class="btn btn-sm <?= $report_type === 'revenue' ? 'btn-islamic fw-bold' : 'btn-outline-secondary' ?>">
                <i class="bi bi-cash-stack me-1"></i> Revenue
            </a>
            <a href="?type=debtors" class="btn btn-sm <?= $report_type === 'debtors' ? 'btn-islamic fw-bold' : 'btn-outline-secondary' ?>">
                <i class="bi bi-exclamation-triangle me-1"></i> Fee Debtors
            </a>
        </div>
        <a href="?type=<?= e($report_type) ?>&export=csv" class="btn btn-gold btn-sm shadow-sm">
            <i class="bi bi-filetype-csv me-1"></i> Export Report (CSV)
        </a>
    </div>
</div>

<?php if ($report_type === 'enrolment'): ?>
    <!-- Enrolment Report -->
    <div class="card card-modern mb-4">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
            <h5 class="mb-0 text-islamic fw-bold">Student Enrolment by Class & Gender</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Islamiyya Class</th>
                            <th>Stream Category</th>
                            <th class="text-center">Boys (Male)</th>
                            <th class="text-center">Girls (Female)</th>
                            <th class="text-end pe-4">Total Students</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_all = 0; $total_m = 0; $total_f = 0;
                        foreach ($class_enrolment as $ce): 
                            $total_all += $ce['total_students'];
                            $total_m += $ce['male_count'];
                            $total_f += $ce['female_count'];
                        ?>
                            <tr>
                                <td class="ps-4 fw-bold text-islamic"><?= e($ce['class_name']) ?></td>
                                <td><span class="badge bg-gold text-dark"><?= e($ce['level_category']) ?></span></td>
                                <td class="text-center fw-semibold text-primary"><?= $ce['male_count'] ?></td>
                                <td class="text-center fw-semibold text-danger"><?= $ce['female_count'] ?></td>
                                <td class="text-end pe-4 fw-bold text-dark fs-6"><?= $ce['total_students'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <th class="ps-4" colspan="2">Total Enrolled across Madrasah:</th>
                            <th class="text-center text-primary fs-6"><?= $total_m ?></th>
                            <th class="text-center text-danger fs-6"><?= $total_f ?></th>
                            <th class="text-end pe-4 fs-5 text-islamic fw-bold"><?= $total_all ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

<?php elseif ($report_type === 'revenue'): ?>
    <!-- Revenue Report -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card stat-card p-3">
                <span class="text-muted small fw-bold">Total Assessed Fees</span>
                <h4 class="fw-bold text-dark mt-1 mb-0"><?= format_currency($fin_summary['total_assessed']) ?></h4>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card gold-border p-3">
                <span class="text-muted small fw-bold">Collected Revenue</span>
                <h4 class="fw-bold text-success mt-1 mb-0"><?= format_currency($fin_summary['total_collected']) ?></h4>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card danger-border p-3">
                <span class="text-muted small fw-bold">Uncollected Balance</span>
                <h4 class="fw-bold text-danger mt-1 mb-0"><?= format_currency($fin_summary['total_debt']) ?></h4>
            </div>
        </div>
    </div>

    <div class="card card-modern">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 text-islamic fw-bold">Revenue Breakdown by Payment Channel</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Channel / Method</th>
                            <th>Total Transactions</th>
                            <th class="text-end pe-4">Total Amount Received (₦)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rev_methods as $rm): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-islamic"><?= e(str_replace('_', ' ', strtoupper($rm['payment_method']))) ?></td>
                                <td><?= $rm['tx_count'] ?> Transactions</td>
                                <td class="text-end pe-4 fw-bold text-success fs-6"><?= format_currency($rm['total_amount']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

<?php elseif ($report_type === 'debtors'): ?>
    <!-- Fee Debtors Report -->
    <div class="card card-modern">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
            <h5 class="mb-0 text-danger fw-bold">Outstanding Fee Debtors Report</h5>
            <span class="badge bg-danger">Total Outstanding: <?= format_currency($fin_summary['total_debt']) ?></span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Student Name (ID)</th>
                            <th>Class</th>
                            <th>Parent Name & Phone</th>
                            <th>Total Assessed</th>
                            <th>Amount Paid</th>
                            <th class="text-end pe-4">Outstanding Debt (₦)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $stmt_d = $pdo->query("
                            SELECT s.student_id_number, s.first_name, s.last_name, c.class_name,
                                   pt.full_name as parent_name, u.phone as parent_phone, inv.total_amount, inv.amount_paid, inv.balance
                            FROM student_invoices inv
                            JOIN students s ON inv.student_id = s.id
                            LEFT JOIN classes c ON s.current_class_id = c.id
                            JOIN parents pt ON s.parent_id = pt.id
                            JOIN users u ON pt.user_id = u.id
                            WHERE inv.balance > 0
                            ORDER BY inv.balance DESC
                        ");
                        $debtors = $stmt_d->fetchAll();
                        if (empty($debtors)): ?>
                            <tr><td colspan="6" class="text-center py-4 text-success fw-bold">No outstanding fee balances!</td></tr>
                        <?php else:
                            foreach ($debtors as $d): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-bold"><?= e($d['first_name'] . ' ' . $d['last_name']) ?></div>
                                        <small class="text-muted"><?= e($d['student_id_number'] ?? 'Pending') ?></small>
                                    </td>
                                    <td><?= e($d['class_name'] ?? 'Class') ?></td>
                                    <td><?= e($d['parent_name']) ?> (<?= e($d['parent_phone']) ?>)</td>
                                    <td><?= format_currency($d['total_amount']) ?></td>
                                    <td class="text-success"><?= format_currency($d['amount_paid']) ?></td>
                                    <td class="text-end pe-4 fw-bold text-danger fs-6"><?= format_currency($d['balance']) ?></td>
                                </tr>
                            <?php endforeach;
                        endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
