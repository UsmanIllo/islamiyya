<?php
$page_title = "Parent Dashboard";
$active_tab = "dashboard";
require_once __DIR__ . '/../includes/parent_header.php';

$pdo = get_db_connection();
$parent_id = (int)$parent['id'];
$cur_term = get_current_session_term();

// 1. Fetch parent's children
$stmt_children = $pdo->prepare("
    SELECT s.*, c.class_name, rc.class_name as requested_class_name
    FROM students s
    LEFT JOIN classes c ON s.current_class_id = c.id
    LEFT JOIN classes rc ON s.requested_class_id = rc.id
    WHERE s.parent_id = ?
    ORDER BY s.id DESC
");
$stmt_children->execute([$parent_id]);
$children = $stmt_children->fetchAll();
$total_children = count($children);

// 2. Financial Totals for Parent
$stmt_fin = $pdo->prepare("
    SELECT 
        COALESCE(SUM(inv.total_amount), 0) as total_assessed,
        COALESCE(SUM(inv.amount_paid), 0) as total_paid,
        COALESCE(SUM(inv.balance), 0) as total_balance
    FROM student_invoices inv
    JOIN students s ON inv.student_id = s.id
    WHERE s.parent_id = ?
");
$stmt_fin->execute([$parent_id]);
$fin_summary = $stmt_fin->fetch();

// 3. Recent Payments for Parent
$stmt_pay = $pdo->prepare("
    SELECT p.*, s.first_name, s.last_name, s.student_id_number, r.receipt_number
    FROM payments p
    JOIN students s ON p.student_id = s.id
    LEFT JOIN receipts r ON p.id = r.payment_id
    WHERE p.parent_id = ?
    ORDER BY p.id DESC
    LIMIT 5
");
$stmt_pay->execute([$parent_id]);
$recent_payments = $stmt_pay->fetchAll();

// 4. Recent Announcements for Parent
$stmt_ann = $pdo->prepare("
    SELECT n.*, nr.is_read
    FROM notifications n
    JOIN notification_recipients nr ON n.id = nr.notification_id
    WHERE nr.user_id = ?
    ORDER BY n.created_at DESC
    LIMIT 3
");
$stmt_ann->execute([$_SESSION['user_id']]);
$announcements = $stmt_ann->fetchAll();
?>

<!-- Welcome Banner -->
<div class="card card-modern border-0 mb-4 bg-islamic text-white overflow-hidden shadow-sm">
    <div class="card-body p-4 p-md-5 position-relative">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <span class="badge bg-gold text-dark mb-2 fw-semibold px-3 py-1 rounded-pill">
                    <i class="bi bi-calendar-check me-1"></i> <?= e($cur_term['session_name']) ?> &bull; <?= e($cur_term['term_name']) ?>
                </span>
                <h2 class="fw-bold mb-2">Assalamu Alaikum, <?= e($parent['full_name']) ?>!</h2>
                <p class="text-white-50 mb-4">
                    Welcome to your Islamiyya parent portal. Manage your children's registrations, monitor curriculum levels, view fee assessments in Nigerian Naira (₦), and pay securely.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <a href="<?= BASE_URL ?>/parent/register_child.php" class="btn btn-gold shadow-sm">
                        <i class="bi bi-person-plus-fill me-1"></i> Register a Child
                    </a>
                    <a href="<?= BASE_URL ?>/parent/pay_fee.php" class="btn btn-outline-light">
                        <i class="bi bi-credit-card me-1"></i> Pay School Fees
                    </a>
                </div>
            </div>
            <div class="col-lg-4 d-none d-lg-block text-end opacity-25">
                <i class="bi bi-book-half" style="font-size: 8rem;"></i>
            </div>
        </div>
    </div>
</div>

<!-- Summary Metric Cards -->
<div class="row g-4 mb-3">
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card p-4">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small text-uppercase fw-bold">Registered Children</span>
                    <h3 class="fw-bold text-islamic mb-0 mt-1"><?= $total_children ?></h3>
                </div>
                <div class="stat-icon bg-islamic text-white">
                    <i class="bi bi-people-fill"></i>
                </div>
            </div>
            <div class="mt-3 small text-muted">
                <a href="<?= BASE_URL ?>/parent/my_children.php" class="text-decoration-none text-islamic fw-semibold">
                    View children profiles <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card info-border p-4">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small text-uppercase fw-bold">Total Fees Assessed</span>
                    <h3 class="fw-bold text-dark mb-0 mt-1"><?= format_currency($fin_summary['total_assessed']) ?></h3>
                </div>
                <div class="stat-icon bg-info bg-opacity-10 text-info">
                    <i class="bi bi-receipt-cutoff"></i>
                </div>
            </div>
            <div class="mt-3 small text-muted">
                Academic Term Invoices
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card gold-border p-4">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small text-uppercase fw-bold">Total Amount Paid</span>
                    <h3 class="fw-bold text-success mb-0 mt-1"><?= format_currency($fin_summary['total_paid']) ?></h3>
                </div>
                <div class="stat-icon bg-success bg-opacity-10 text-success">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
            </div>
            <div class="mt-3 small text-muted">
                <a href="<?= BASE_URL ?>/parent/payment_history.php" class="text-decoration-none text-success fw-semibold">
                    View payment receipts <i class="bi bi-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-xl-3">
        <div class="card stat-card danger-border p-4">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <span class="text-muted small text-uppercase fw-bold">Outstanding Balance</span>
                    <h3 class="fw-bold text-danger mb-0 mt-1"><?= format_currency($fin_summary['total_balance']) ?></h3>
                </div>
                <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                    <i class="bi bi-exclamation-circle-fill"></i>
                </div>
            </div>
            <div class="mt-3 small">
                <?php if ($fin_summary['total_balance'] > 0): ?>
                    <a href="<?= BASE_URL ?>/parent/pay_fee.php" class="text-danger fw-bold text-decoration-none">
                        Pay pending balance now <i class="bi bi-arrow-right"></i>
                    </a>
                <?php else: ?>
                    <span class="text-success fw-semibold"><i class="bi bi-check-all"></i> All Fees Cleared</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Interactive Fee Progress Bar -->
<?php if ($fin_summary['total_assessed'] > 0): ?>
    <?php 
        $pct_cleared = min(100, round(($fin_summary['total_paid'] / $fin_summary['total_assessed']) * 100)); 
    ?>
    <div class="card card-modern p-3 mb-4 bg-white border animate-fade-in-up">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
            <span class="small fw-bold text-islamic">
                <i class="bi bi-pie-chart-fill text-gold me-1"></i> Family Term Fee Clearance Status
            </span>
            <div class="d-flex align-items-center gap-2">
                <span class="badge <?= $pct_cleared >= 100 ? 'bg-success' : 'bg-warning text-dark' ?> fw-semibold">
                    <?= $pct_cleared ?>% Settle Ratio
                </span>
                <?php if ($fin_summary['total_balance'] > 0): ?>
                    <a href="<?= BASE_URL ?>/parent/pay_fee.php" class="btn btn-sm btn-gold py-0 px-2 fw-semibold" style="font-size: 0.78rem;">
                        <i class="bi bi-credit-card me-1"></i> Pay Balance
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <div class="progress" style="height: 10px; border-radius: 20px; background-color: #e9f2ee;">
            <div class="progress-bar bg-success progress-bar-striped progress-bar-animated" role="progressbar" style="width: <?= $pct_cleared ?>%;" aria-valuenow="<?= $pct_cleared ?>" aria-valuemin="0" aria-valuemax="100"></div>
        </div>
        <div class="d-flex justify-content-between text-muted small mt-2">
            <span>Paid: <strong class="text-success"><?= format_currency($fin_summary['total_paid']) ?></strong></span>
            <span>Total Assessed: <strong><?= format_currency($fin_summary['total_assessed']) ?></strong></span>
            <span>Remaining: <strong class="<?= $fin_summary['total_balance'] > 0 ? 'text-danger' : 'text-success' ?>"><?= format_currency($fin_summary['total_balance']) ?></strong></span>
        </div>
    </div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <!-- Children Overview Table -->
    <div class="col-lg-8">
        <div class="card card-modern h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <h5 class="mb-0 text-islamic fw-bold">
                    <i class="bi bi-mortarboard-fill text-gold me-2"></i> My Enrolled Children
                </h5>
                <a href="<?= BASE_URL ?>/parent/register_child.php" class="btn btn-islamic btn-sm">
                    <i class="bi bi-plus-circle me-1"></i> Add Another Child
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Student ID / Name</th>
                                <th>Gender / Age</th>
                                <th>Class / Level</th>
                                <th>Registration Status</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($children)): ?>
                                <tr>
                                    <td colspan="5" class="text-center py-5">
                                        <i class="bi bi-person-x fs-1 text-muted d-block mb-2"></i>
                                        <p class="text-muted mb-3">You have not registered any children yet.</p>
                                        <a href="<?= BASE_URL ?>/parent/register_child.php" class="btn btn-gold btn-sm px-4">
                                            Register Your Child Now
                                        </a>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($children as $child): ?>
                                    <?php 
                                        $age = date_diff(date_create($child['date_of_birth']), date_create('today'))->y;
                                    ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-bold text-islamic">
                                                <?= e($child['first_name'] . ' ' . $child['last_name'] . ' ' . ($child['other_name'] ?? '')) ?>
                                            </div>
                                            <small class="text-muted">
                                                <?= $child['student_id_number'] ? '<span class="badge bg-light text-dark border">' . e($child['student_id_number']) . '</span>' : '<span class="text-warning">ID Pending Approval</span>' ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?= e($child['gender']) ?>
                                            <div class="small text-muted"><?= $age ?> years old</div>
                                        </td>
                                        <td>
                                            <?php if ($child['current_class_id']): ?>
                                                <span class="badge bg-islamic-soft text-islamic"><?= e($child['class_name']) ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-muted border">Requested: <?= e($child['requested_class_name'] ?? 'N/A') ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($child['registration_status'] === 'approved'): ?>
                                                <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i> Approved</span>
                                            <?php elseif ($child['registration_status'] === 'rejected'): ?>
                                                <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i> Rejected</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark"><i class="bi bi-hourglass-split me-1"></i> Pending Approval</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <a href="<?= BASE_URL ?>/parent/child_details.php?id=<?= $child['id'] ?>" class="btn btn-sm btn-outline-islamic">
                                                View Details
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
    </div>

    <!-- Recent Notices Widget -->
    <div class="col-lg-4">
        <div class="card card-modern h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <h5 class="mb-0 text-islamic fw-bold">
                    <i class="bi bi-bell-fill text-gold me-2"></i> School Notices
                </h5>
                <a href="<?= BASE_URL ?>/parent/notifications.php" class="small text-decoration-none text-gold fw-semibold">View All</a>
            </div>
            <div class="card-body p-3">
                <?php if (empty($announcements)): ?>
                    <p class="text-muted small text-center my-4">No recent school announcements.</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($announcements as $ann): ?>
                            <div class="list-group-item px-0 py-3 <?= $ann['is_read'] ? 'opacity-75' : 'fw-semibold' ?>">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <h6 class="mb-0 text-islamic fs-6"><?= e($ann['title']) ?></h6>
                                    <small class="text-muted" style="font-size: 0.75rem;"><?= format_date($ann['created_at']) ?></small>
                                </div>
                                <p class="text-muted small mb-0"><?= e(mb_strimwidth($ann['message'], 0, 95, "...")) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Recent Payments Table -->
<div class="card card-modern">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h5 class="mb-0 text-islamic fw-bold">
            <i class="bi bi-clock-history text-gold me-2"></i> Recent Payment Activities
        </h5>
        <a href="<?= BASE_URL ?>/parent/payment_history.php" class="btn btn-outline-islamic btn-sm">Full Payment History</a>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Reference</th>
                        <th>Student</th>
                        <th>Amount (₦)</th>
                        <th>Method</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Receipt</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_payments)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">No payments recorded yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recent_payments as $pay): ?>
                            <tr>
                                <td class="ps-4 fw-bold font-monospace small"><?= e($pay['payment_reference']) ?></td>
                                <td>
                                    <?= e($pay['first_name'] . ' ' . $pay['last_name']) ?>
                                    <div class="small text-muted"><?= e($pay['student_id_number'] ?? 'ID Pending') ?></div>
                                </td>
                                <td class="fw-bold text-dark"><?= format_currency($pay['amount']) ?></td>
                                <td>
                                    <span class="badge bg-light text-dark border">
                                        <?= e(str_replace('_', ' ', strtoupper($pay['payment_method']))) ?>
                                    </span>
                                </td>
                                <td class="small text-muted"><?= format_date($pay['created_at'], 'd M Y, h:i A') ?></td>
                                <td>
                                    <?php if ($pay['payment_status'] === 'successful'): ?>
                                        <span class="badge bg-success"><i class="bi bi-check2"></i> Successful</span>
                                    <?php elseif ($pay['payment_status'] === 'pending'): ?>
                                        <span class="badge bg-warning text-dark"><i class="bi bi-clock"></i> Verification Pending</span>
                                    <?php elseif ($pay['payment_status'] === 'rejected'): ?>
                                        <span class="badge bg-danger"><i class="bi bi-x"></i> Rejected</span>
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

<?php require_once __DIR__ . '/../includes/parent_footer.php'; ?>
