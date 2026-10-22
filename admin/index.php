<?php
$page_title = "Admin Dashboard";
$active_tab = "dashboard";
require_once __DIR__ . '/../includes/admin_header.php';

$pdo = get_db_connection();
$cur_term = get_current_session_term();

// 1. Core Summary Metrics
$total_parents = (int)$pdo->query("SELECT COUNT(*) FROM parents")->fetchColumn();
$total_students = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE enrolment_status = 'active'")->fetchColumn();
$pending_students = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE registration_status = 'pending'")->fetchColumn();
$total_collected = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE payment_status = 'successful'")->fetchColumn();
$total_outstanding = (float)$pdo->query("SELECT COALESCE(SUM(balance), 0) FROM student_invoices")->fetchColumn();
$pending_payments_count = (int)$pdo->query("SELECT COUNT(*) FROM payments WHERE payment_status = 'pending'")->fetchColumn();

// 2. Recent Payments
$stmt_recent_pay = $pdo->query("
    SELECT p.*, s.first_name, s.last_name, s.student_id_number, pt.full_name as parent_name, r.receipt_number
    FROM payments p
    JOIN students s ON p.student_id = s.id
    JOIN parents pt ON p.parent_id = pt.id
    LEFT JOIN receipts r ON p.id = r.payment_id
    ORDER BY p.id DESC
    LIMIT 6
");
$recent_payments = $stmt_recent_pay->fetchAll();

// 3. Students with Unpaid / Overdue Fees (Top Debtors for quick alert)
$stmt_debtors = $pdo->query("
    SELECT inv.*, s.first_name, s.last_name, s.student_id_number, c.class_name, pt.full_name as parent_name, u.phone as parent_phone
    FROM student_invoices inv
    JOIN students s ON inv.student_id = s.id
    LEFT JOIN classes c ON s.current_class_id = c.id
    JOIN parents pt ON s.parent_id = pt.id
    JOIN users u ON pt.user_id = u.id
    WHERE inv.balance > 0
    ORDER BY inv.balance DESC
    LIMIT 5
");
$fee_debtors = $stmt_debtors->fetchAll();

// 4. Enrolment Statistics by Class
$stmt_enrolment = $pdo->query("
    SELECT c.id, c.class_name, c.level_category, COUNT(s.id) as student_count
    FROM classes c
    LEFT JOIN students s ON s.current_class_id = c.id AND s.enrolment_status = 'active'
    WHERE c.status = 'active'
    GROUP BY c.id
    ORDER BY c.display_order ASC
");
$class_stats = $stmt_enrolment->fetchAll();
?>

<style>
/* ── Page Header ────────────────────────────────────────────── */
.page-header {
    position: relative;
    background: linear-gradient(135deg, #1a5c38 0%, #2e8b57 55%, #276749 100%);
    border-radius: 1rem;
    padding: 2rem 2rem 1.6rem;
    margin-bottom: 1.75rem;
    overflow: hidden;
    box-shadow: 0 4px 24px rgba(30,90,60,.18);
}
.page-header::before {
    content: '';
    position: absolute;
    inset: 0;
    background-image:
        repeating-linear-gradient(60deg, rgba(255,255,255,.04) 0px, rgba(255,255,255,.04) 1px, transparent 1px, transparent 30px),
        repeating-linear-gradient(-60deg, rgba(255,255,255,.04) 0px, rgba(255,255,255,.04) 1px, transparent 1px, transparent 30px),
        repeating-linear-gradient(0deg, rgba(255,255,255,.03) 0px, rgba(255,255,255,.03) 1px, transparent 1px, transparent 30px);
    pointer-events: none;
}
.page-header .breadcrumb { --bs-breadcrumb-divider-color: rgba(255,255,255,.5); }
.page-header .breadcrumb-item, .page-header .breadcrumb-item a { color: rgba(255,255,255,.75); text-decoration: none; font-size: .8rem; }
.page-header .breadcrumb-item.active { color: #ffd700; }
.page-header h2 { color: #fff; letter-spacing: .3px; }
.page-header p   { color: rgba(255,255,255,.8); }

/* ── Stat Cards ─────────────────────────────────────────────── */
.stat-card {
    border: none;
    border-radius: .9rem;
    box-shadow: 0 2px 16px rgba(0,0,0,.07);
    position: relative;
    overflow: hidden;
    transition: transform .2s, box-shadow .2s;
}
.stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 28px rgba(0,0,0,.13); }
.stat-card::after {
    content: '';
    position: absolute;
    bottom: 0; left: 0; right: 0;
    height: 4px;
    border-radius: 0 0 .9rem .9rem;
}
.stat-card-green::after  { background: linear-gradient(90deg, #1a5c38, #2e8b57); }
.stat-card-gold::after   { background: linear-gradient(90deg, #b8860b, #ffd700); }
.stat-card-danger::after { background: linear-gradient(90deg, #7f1d1d, #dc3545); }
.stat-card-info::after   { background: linear-gradient(90deg, #0e4f72, #0d9fc2); }

.stat-icon-bg {
    font-size: 4rem;
    opacity: .12;
    line-height: 1;
    color: #1a5c38;
    user-select: none;
    flex-shrink: 0;
}
.stat-card-gold   .stat-icon-bg { color: #b8860b; }
.stat-card-danger .stat-icon-bg { color: #dc3545; }
.stat-card-info   .stat-icon-bg { color: #0d9fc2; }

.letter-spacing-1 { letter-spacing: .06em; }

/* ── Chart Cards ────────────────────────────────────────────── */
.card-modern {
    border: none;
    border-radius: .9rem;
    box-shadow: 0 2px 16px rgba(0,0,0,.07);
}
.card-modern .card-header {
    background: #fff;
    border-bottom: 1px solid #f0f0f0;
    border-radius: .9rem .9rem 0 0 !important;
    font-weight: 700;
    color: #1a5c38;
    padding: 1rem 1.25rem;
}

/* ── Quick Action Cards ─────────────────────────────────────── */
.quick-action-card {
    border: none;
    border-radius: .9rem;
    box-shadow: 0 2px 12px rgba(0,0,0,.07);
    transition: transform .2s, box-shadow .2s;
    text-decoration: none;
    display: block;
    padding: 1.4rem 1rem 1.2rem;
    text-align: center;
    background: #fff;
}
.quick-action-card:hover { transform: translateY(-4px); box-shadow: 0 10px 30px rgba(0,0,0,.12); text-decoration: none; }
.quick-action-card .qa-icon {
    width: 54px; height: 54px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.5rem;
    margin: 0 auto .75rem;
}

/* ── Table enhancements ─────────────────────────────────────── */
.table-islamic thead th { background: #f4faf7; color: #1a5c38; font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; border-bottom: 2px solid #c8e6d5; }
.table-search-input { border-radius: 2rem; border: 1.5px solid #d4e9dd; font-size: .875rem; padding: .4rem 1rem; }
.table-search-input:focus { border-color: #2e8b57; box-shadow: 0 0 0 .2rem rgba(46,139,87,.15); outline: none; }

.status-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 5px; vertical-align: middle; }
.status-dot-success { background: #28a745; }
.status-dot-warning { background: #ffc107; }
.status-dot-danger  { background: #dc3545; }
.status-dot-secondary { background: #6c757d; }

/* Green top border for recent payments card */
.card-payments-header { border-top: 4px solid #2e8b57; border-radius: .9rem .9rem 0 0 !important; }
/* Red gradient top border for debtors card */
.card-debtors-header  { border-top: 4px solid; border-image: linear-gradient(90deg, #7f1d1d, #dc3545) 1; border-radius: 0 !important; }

/* ── Enrolment progress bars ────────────────────────────────── */
.enrolment-bar-wrap .progress { height: 8px; border-radius: 4px; background: #e8f5ee; }
.enrolment-bar-wrap .progress-bar { background: linear-gradient(90deg, #1a5c38, #2e8b57); border-radius: 4px; }

/* ── Debtor mini progress ───────────────────────────────────── */
.debt-progress { height: 6px; border-radius: 3px; background: #fde8e8; }
.debt-progress .progress-bar { background: linear-gradient(90deg, #7f1d1d, #dc3545); }

/* ── Animations ─────────────────────────────────────────────── */
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(18px); }
    to   { opacity: 1; transform: translateY(0); }
}
.animate-fade-in-up { animation: fadeInUp .45s ease both; }
.delay-1 { animation-delay: .07s; } .delay-2 { animation-delay: .14s; }
.delay-3 { animation-delay: .21s; } .delay-4 { animation-delay: .28s; }
.delay-5 { animation-delay: .35s; } .delay-6 { animation-delay: .42s; }
</style>

<!-- ═══════════════════════════ PAGE HEADER ═══════════════════════════ -->
<div class="page-header animate-fade-in-up mb-4">
    <nav aria-label="breadcrumb" class="mb-2">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><i class="bi bi-house-door-fill me-1"></i>Home</li>
            <li class="breadcrumb-item active">Dashboard</li>
        </ol>
    </nav>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div>
            <h2 class="fw-bold mb-1">
                <i class="bi bi-speedometer2 me-2" style="color:#ffd700;"></i>Administrative Dashboard
            </h2>
            <p class="mb-0 small">Overview of student enrolment, school fee revenue in Nigerian Naira (₦), and daily activities.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= BASE_URL ?>/admin/registrations.php" class="btn btn-warning text-dark btn-sm fw-semibold shadow-sm">
                <i class="bi bi-person-check me-1"></i> Review Applications (<?= $pending_students ?>)
            </a>
            <a href="<?= BASE_URL ?>/admin/verify_payments.php" class="btn btn-sm fw-semibold shadow-sm" style="background:#ffd700;color:#1a1a1a;">
                <i class="bi bi-cash-coin me-1"></i> Verify Payments (<?= $pending_payments_count ?>)
            </a>
            <a href="<?= BASE_URL ?>/admin/reports.php" class="btn btn-outline-light btn-sm">
                <i class="bi bi-file-earmark-bar-graph me-1"></i> Reports
            </a>
        </div>
    </div>
</div>

<!-- ═══════════════════════════ STAT CARDS ════════════════════════════ -->
<div class="row g-3 mb-4">

    <!-- Parents -->
    <div class="col-sm-6 col-xl-4 col-xxl-2">
        <div class="card stat-card stat-card-green p-4 animate-fade-in-up delay-1 h-100">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <span class="text-muted small fw-bold text-uppercase letter-spacing-1">Total Parents</span>
                    <div class="stat-counter display-6 fw-bold text-islamic mt-1 mb-0" data-target="<?= $total_parents ?>">0</div>
                    <small class="text-muted mt-1 d-block">
                        <a href="<?= BASE_URL ?>/admin/parents.php" class="text-islamic text-decoration-none fw-semibold">
                            View all <i class="bi bi-arrow-right-short"></i>
                        </a>
                    </small>
                </div>
                <div class="stat-icon-bg"><i class="bi bi-people-fill"></i></div>
            </div>
        </div>
    </div>

    <!-- Active Students -->
    <div class="col-sm-6 col-xl-4 col-xxl-2">
        <div class="card stat-card stat-card-green p-4 animate-fade-in-up delay-2 h-100">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <span class="text-muted small fw-bold text-uppercase letter-spacing-1">Active Students</span>
                    <div class="stat-counter display-6 fw-bold text-islamic mt-1 mb-0" data-target="<?= $total_students ?>">0</div>
                    <small class="text-muted mt-1 d-block">
                        <a href="<?= BASE_URL ?>/admin/students.php" class="text-islamic text-decoration-none fw-semibold">
                            View all <i class="bi bi-arrow-right-short"></i>
                        </a>
                    </small>
                </div>
                <div class="stat-icon-bg"><i class="bi bi-mortarboard-fill"></i></div>
            </div>
        </div>
    </div>

    <!-- Pending Registrations -->
    <div class="col-sm-6 col-xl-4 col-xxl-2">
        <div class="card stat-card stat-card-gold p-4 animate-fade-in-up delay-3 h-100">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <span class="text-muted small fw-bold text-uppercase letter-spacing-1">Pending Registrations</span>
                    <div class="stat-counter display-6 fw-bold text-warning mt-1 mb-0" data-target="<?= $pending_students ?>">0</div>
                    <small class="text-muted mt-1 d-block">
                        <a href="<?= BASE_URL ?>/admin/registrations.php" class="text-warning text-decoration-none fw-semibold">
                            Approve now <i class="bi bi-arrow-right-short"></i>
                        </a>
                    </small>
                </div>
                <div class="stat-icon-bg"><i class="bi bi-person-plus-fill"></i></div>
            </div>
        </div>
    </div>

    <!-- Pending Payments -->
    <div class="col-sm-6 col-xl-4 col-xxl-2">
        <div class="card stat-card stat-card-danger p-4 animate-fade-in-up delay-4 h-100">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <span class="text-muted small fw-bold text-uppercase letter-spacing-1">Pending Payments</span>
                    <div class="stat-counter display-6 fw-bold text-danger mt-1 mb-0" data-target="<?= $pending_payments_count ?>">0</div>
                    <small class="text-muted mt-1 d-block">
                        <a href="<?= BASE_URL ?>/admin/verify_payments.php" class="text-danger text-decoration-none fw-semibold">
                            Needs review <i class="bi bi-arrow-right-short"></i>
                        </a>
                    </small>
                </div>
                <div class="stat-icon-bg"><i class="bi bi-hourglass-split"></i></div>
            </div>
        </div>
    </div>

    <!-- Fees Collected -->
    <div class="col-sm-6 col-xl-4 col-xxl-2">
        <div class="card stat-card stat-card-green p-4 animate-fade-in-up delay-5 h-100">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <span class="text-muted small fw-bold text-uppercase letter-spacing-1">Fees Collected</span>
                    <div class="display-6 fw-bold text-islamic mt-1 mb-1" style="font-size:1.45rem!important;"><?= format_currency($total_collected) ?></div>
                    <small class="text-success mt-1 d-block fw-semibold">
                        <i class="bi bi-check-circle-fill me-1"></i>Confirmed payments
                    </small>
                </div>
                <div class="stat-icon-bg"><i class="bi bi-cash-stack"></i></div>
            </div>
        </div>
    </div>

    <!-- Outstanding Balance -->
    <div class="col-sm-6 col-xl-4 col-xxl-2">
        <div class="card stat-card stat-card-danger p-4 animate-fade-in-up delay-6 h-100">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <span class="text-muted small fw-bold text-uppercase letter-spacing-1">Outstanding Balance</span>
                    <div class="display-6 fw-bold text-danger mt-1 mb-1" style="font-size:1.45rem!important;"><?= format_currency($total_outstanding) ?></div>
                    <small class="text-danger mt-1 d-block fw-semibold">
                        <i class="bi bi-exclamation-circle-fill me-1"></i>Unpaid balances
                    </small>
                </div>
                <div class="stat-icon-bg"><i class="bi bi-wallet2"></i></div>
            </div>
        </div>
    </div>

</div>

<!-- ═══════════════════════════ CHARTS ROW ════════════════════════════ -->
<div class="row g-4 mb-4">
    <div class="col-lg-8">
        <div class="card card-modern h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-graph-up-arrow text-gold me-2"></i>Monthly Revenue Trend</span>
                <span class="badge bg-success bg-opacity-10 text-success small fw-semibold">Last 6 Months</span>
            </div>
            <div class="card-body">
                <canvas id="revenueChart" height="100"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card card-modern h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-pie-chart-fill text-gold me-2"></i>Class Enrolment</span>
                <a href="<?= BASE_URL ?>/admin/classes.php" class="small text-islamic text-decoration-none fw-semibold">Manage</a>
            </div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <canvas id="enrolmentChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- ═══════════════════════════ QUICK ACTIONS ═════════════════════════ -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <p class="text-muted small fw-bold text-uppercase letter-spacing-1 mb-2"><i class="bi bi-lightning-fill text-warning me-1"></i>Quick Actions</p>
    </div>
    <div class="col-6 col-md-3">
        <a href="<?= BASE_URL ?>/admin/registrations.php" class="quick-action-card animate-fade-in-up delay-1">
            <div class="qa-icon" style="background:#fef9e7;">
                <i class="bi bi-person-check-fill" style="color:#b8860b;"></i>
            </div>
            <div class="fw-bold text-dark small">Approve Registrations</div>
            <div class="text-muted" style="font-size:.75rem;">Review pending applications</div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="<?= BASE_URL ?>/admin/verify_payments.php" class="quick-action-card animate-fade-in-up delay-2">
            <div class="qa-icon" style="background:#e8f5ee;">
                <i class="bi bi-cash-coin" style="color:#1a5c38;"></i>
            </div>
            <div class="fw-bold text-dark small">Verify Payments</div>
            <div class="text-muted" style="font-size:.75rem;">Confirm bank transfers</div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="<?= BASE_URL ?>/admin/notifications.php" class="quick-action-card animate-fade-in-up delay-3">
            <div class="qa-icon" style="background:#eaf4fb;">
                <i class="bi bi-megaphone-fill" style="color:#0d6efd;"></i>
            </div>
            <div class="fw-bold text-dark small">Send Announcement</div>
            <div class="text-muted" style="font-size:.75rem;">Notify parents & staff</div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="<?= BASE_URL ?>/admin/reports.php" class="quick-action-card animate-fade-in-up delay-4">
            <div class="qa-icon" style="background:#fdf0f0;">
                <i class="bi bi-file-earmark-bar-graph-fill" style="color:#dc3545;"></i>
            </div>
            <div class="fw-bold text-dark small">Generate Reports</div>
            <div class="text-muted" style="font-size:.75rem;">Export & analyse data</div>
        </a>
    </div>
</div>

<!-- ═══════════════════════════ PAYMENTS + ENROLMENT ═════════════════ -->
<div class="row g-4 mb-4">

    <!-- Recent Payments -->
    <div class="col-lg-8">
        <div class="card card-modern h-100">
            <div class="card-header card-payments-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0 text-islamic fw-bold">
                    <i class="bi bi-credit-card-fill text-gold me-2"></i>Recent Payment Transactions
                </h6>
                <a href="<?= BASE_URL ?>/admin/payments.php" class="btn btn-outline-islamic btn-sm">All Transactions</a>
            </div>
            <div class="card-body pt-3 pb-0 px-3">
                <input type="search" class="form-control table-search-input mb-3" placeholder="&#xF52A;  Search by reference, student or parent…" data-target-table="recentPaymentsTable">
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 table-islamic" id="recentPaymentsTable">
                        <thead>
                            <tr>
                                <th class="ps-4">Reference</th>
                                <th>Student &amp; Parent</th>
                                <th>Amount (₦)</th>
                                <th>Method</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_payments)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">No payments recorded yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_payments as $pay): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="font-monospace fw-bold small text-islamic"><?= e($pay['payment_reference']) ?></div>
                                            <small class="text-muted"><?= format_date($pay['created_at'], 'd M, h:i A') ?></small>
                                        </td>
                                        <td>
                                            <div class="fw-bold"><?= e($pay['first_name'] . ' ' . $pay['last_name']) ?></div>
                                            <small class="text-muted"><?= e($pay['parent_name']) ?></small>
                                        </td>
                                        <td class="fw-bold text-dark"><?= format_currency($pay['amount']) ?></td>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                <?= e(str_replace('_', ' ', strtoupper($pay['payment_method']))) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($pay['payment_status'] === 'successful'): ?>
                                                <span class="status-dot status-dot-success"></span>
                                                <span class="badge bg-success">Successful</span>
                                            <?php elseif ($pay['payment_status'] === 'pending'): ?>
                                                <span class="status-dot status-dot-warning"></span>
                                                <span class="badge bg-warning text-dark">Pending Verification</span>
                                            <?php elseif ($pay['payment_status'] === 'rejected'): ?>
                                                <span class="status-dot status-dot-danger"></span>
                                                <span class="badge bg-danger">Rejected</span>
                                            <?php else: ?>
                                                <span class="status-dot status-dot-secondary"></span>
                                                <span class="badge bg-secondary"><?= e(ucfirst($pay['payment_status'])) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-4">
                                            <?php if ($pay['payment_status'] === 'pending'): ?>
                                                <a href="<?= BASE_URL ?>/admin/verify_payments.php" class="btn btn-sm btn-gold">Verify</a>
                                            <?php elseif ($pay['payment_status'] === 'successful'): ?>
                                                <a href="<?= BASE_URL ?>/parent/receipt.php?id=<?= $pay['id'] ?>" class="btn btn-sm btn-outline-islamic" target="_blank">Receipt</a>
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

    <!-- Class Enrolment Panel (progress bars) -->
    <div class="col-lg-4">
        <div class="card card-modern h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0 text-islamic fw-bold">
                    <i class="bi bi-bar-chart-fill text-gold me-2"></i>Class Enrolment
                </h6>
                <a href="<?= BASE_URL ?>/admin/classes.php" class="small text-islamic text-decoration-none fw-semibold">Manage</a>
            </div>
            <div class="card-body p-3">
                <?php
                $max_class_count = !empty($class_stats) ? max(array_column($class_stats, 'student_count')) : 1;
                $max_class_count = max($max_class_count, 1);
                ?>
                <div class="enrolment-bar-wrap">
                    <?php foreach ($class_stats as $cs):
                        $pct = round(($cs['student_count'] / $max_class_count) * 100);
                    ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <div>
                                <span class="fw-semibold text-islamic small"><?= e($cs['class_name']) ?></span>
                                <span class="text-muted ms-1" style="font-size:.72rem;"><?= e($cs['level_category']) ?></span>
                            </div>
                            <span class="badge rounded-pill" style="background:#1a5c38;font-size:.78rem;"><?= $cs['student_count'] ?></span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar" role="progressbar" style="width:<?= $pct ?>%"
                                 aria-valuenow="<?= $cs['student_count'] ?>" aria-valuemin="0" aria-valuemax="<?= $max_class_count ?>"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($class_stats)): ?>
                        <p class="text-muted text-center small py-3">No class data available.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- ═══════════════════════════ FEE DEBTORS ══════════════════════════ -->
<div class="card card-modern mb-4">
    <div class="card-header card-debtors-header d-flex justify-content-between align-items-center" style="border-top:4px solid #dc3545;">
        <div>
            <h6 class="mb-0 text-danger fw-bold">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>Outstanding Fee Alerts
            </h6>
            <small class="text-muted">Students with pending fees for academic sessions</small>
        </div>
        <a href="<?= BASE_URL ?>/admin/alerts.php" class="btn btn-outline-danger btn-sm">
            <i class="bi bi-bell-fill me-1"></i>Send Reminders
        </a>
    </div>
    <div class="card-body pt-3 pb-0 px-3">
        <input type="search" class="form-control table-search-input mb-3" placeholder="&#xF52A;  Filter by student, class or parent…" data-target-table="feeDebtorsTable">
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 table-islamic" id="feeDebtorsTable">
                <thead>
                    <tr>
                        <th class="ps-4">Student ID / Name</th>
                        <th>Class</th>
                        <th>Parent / Guardian</th>
                        <th>Phone Number</th>
                        <th>Total Assessed</th>
                        <th>Amount Paid</th>
                        <th>Outstanding Debt (₦)</th>
                        <th class="text-end pe-4">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($fee_debtors)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-success fw-bold">
                                <i class="bi bi-check-circle-fill me-1"></i> Alhamdulillah, no outstanding fee balances!
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($fee_debtors as $deb):
                            $debt_pct = ($deb['total_amount'] > 0) ? round(($deb['balance'] / $deb['total_amount']) * 100) : 0;
                        ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-islamic"><?= e($deb['first_name'] . ' ' . $deb['last_name']) ?></div>
                                    <small class="text-muted"><?= e($deb['student_id_number'] ?? 'Pending') ?></small>
                                </td>
                                <td><?= e($deb['class_name'] ?? 'Not Assigned') ?></td>
                                <td><?= e($deb['parent_name']) ?></td>
                                <td><a href="tel:<?= e($deb['parent_phone']) ?>" class="text-decoration-none"><?= e($deb['parent_phone']) ?></a></td>
                                <td><?= format_currency($deb['total_amount']) ?></td>
                                <td class="text-success"><?= format_currency($deb['amount_paid']) ?></td>
                                <td>
                                    <span class="fw-bold text-danger d-block mb-1"><?= format_currency($deb['balance']) ?></span>
                                    <div class="debt-progress">
                                        <div class="progress-bar" role="progressbar" style="width:<?= $debt_pct ?>%"
                                             aria-valuenow="<?= $debt_pct ?>" aria-valuemin="0" aria-valuemax="100"
                                             title="<?= $debt_pct ?>% unpaid"></div>
                                    </div>
                                    <small class="text-muted" style="font-size:.7rem;"><?= $debt_pct ?>% unpaid</small>
                                </td>
                                <td class="text-end pe-4">
                                    <a href="<?= BASE_URL ?>/admin/student_view.php?id=<?= $deb['student_id'] ?>" class="btn btn-sm btn-outline-islamic">
                                        View Statement
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

<?php
// ── Monthly revenue data (last 6 months) ────────────────────────────
$monthly_revenue = [];
$monthly_labels  = [];
for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $label = date('M Y',  strtotime("-$i months"));
    $stmt  = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE payment_status='successful' AND DATE_FORMAT(created_at,'%Y-%m')=?");
    $stmt->execute([$month]);
    $monthly_revenue[] = (float)$stmt->fetchColumn();
    $monthly_labels[]  = $label;
}
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
/* ── Chart data ─────────────────────────────────────────── */
window.CHART_DATA = {
    revenueChart: {
        labels: <?= json_encode($monthly_labels) ?>,
        data:   <?= json_encode($monthly_revenue) ?>
    },
    enrolmentChart: {
        labels: <?= json_encode(array_column($class_stats, 'class_name')) ?>,
        data:   <?= json_encode(array_column($class_stats, 'student_count')) ?>
    }
};

/* ── Animated stat counters ─────────────────────────────── */
document.querySelectorAll('.stat-counter[data-target]').forEach(el => {
    const target = parseFloat(el.dataset.target) || 0;
    const isInt  = Number.isInteger(target);
    let start    = 0;
    const dur    = 1200;
    const step   = 16;
    const inc    = target / (dur / step);
    const timer  = setInterval(() => {
        start = Math.min(start + inc, target);
        el.textContent = isInt ? Math.round(start).toLocaleString() : start.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
        if (start >= target) clearInterval(timer);
    }, step);
});

/* ── Revenue line chart ─────────────────────────────────── */
(function () {
    const ctx = document.getElementById('revenueChart').getContext('2d');
    const d   = window.CHART_DATA.revenueChart;
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: d.labels,
            datasets: [{
                label: 'Revenue (₦)',
                data: d.data,
                borderColor: '#2e8b57',
                backgroundColor: 'rgba(46,139,87,.10)',
                borderWidth: 2.5,
                pointBackgroundColor: '#1a5c38',
                pointRadius: 4,
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => ' ₦' + ctx.parsed.y.toLocaleString(undefined, {minimumFractionDigits:2})
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: v => '₦' + Number(v).toLocaleString()
                    },
                    grid: { color: 'rgba(0,0,0,.05)' }
                },
                x: { grid: { display: false } }
            }
        }
    });
})();

/* ── Enrolment doughnut chart ───────────────────────────── */
(function () {
    const ctx = document.getElementById('enrolmentChart').getContext('2d');
    const d   = window.CHART_DATA.enrolmentChart;
    const palette = ['#1a5c38','#2e8b57','#3aac6e','#ffd700','#b8860b','#0d9fc2','#dc3545','#6f42c1','#fd7e14','#20c997'];
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: d.labels,
            datasets: [{
                data: d.data,
                backgroundColor: palette,
                borderWidth: 2,
                borderColor: '#fff',
                hoverOffset: 8
            }]
        },
        options: {
            responsive: true,
            cutout: '62%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: { boxWidth: 12, padding: 10, font: { size: 11 } }
                }
            }
        }
    });
})();

/* ── Live table search ──────────────────────────────────── */
document.querySelectorAll('.table-search-input').forEach(input => {
    input.addEventListener('input', function () {
        const q     = this.value.toLowerCase().trim();
        const table = document.getElementById(this.dataset.targetTable);
        if (!table) return;
        table.querySelectorAll('tbody tr').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
