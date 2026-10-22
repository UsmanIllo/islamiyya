<?php
require_once __DIR__ . '/auth.php';
require_admin();

$school_name = get_setting('school_name', 'Darul Huda Islamic Academy');
$cur_info = get_current_session_term();
$pending_payments = get_pending_payment_count();
$pending_registrations = get_pending_registration_count();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? e($page_title) . " | " : "" ?>Admin Portal - <?= e($school_name) ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Amiri:wght@700&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom Theme -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>

<!-- Top Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark navbar-islamic sticky-top py-2 px-3">
    <div class="container-fluid">
        <button class="btn btn-outline-light d-lg-none me-2" type="button" data-bs-toggle="collapse" data-bs-target="#adminSidebar">
            <i class="bi bi-list fs-5"></i>
        </button>
        <a class="navbar-brand d-flex align-items-center" href="<?= BASE_URL ?>/admin/index.php">
            <div class="stat-icon bg-gold text-white me-2" style="width: 38px; height: 38px; font-size: 1.2rem;">
                <i class="bi bi-shield-lock-fill"></i>
            </div>
            <div>
                <span class="navbar-brand-text d-block lh-1 fs-6"><?= e($school_name) ?></span>
                <span class="navbar-brand-subtitle">Administrator Portal</span>
            </div>
        </a>

        <!-- Current Session Info Badge -->
        <div class="d-none d-md-flex align-items-center bg-dark bg-opacity-25 px-3 py-1 rounded-pill text-white small border border-light border-opacity-10">
            <i class="bi bi-calendar-event text-gold me-2"></i>
            <span>Session: <strong><?= e($cur_info['session_name']) ?></strong> &bull; <?= e($cur_info['term_name']) ?></span>
        </div>

        <div class="ms-auto d-flex align-items-center gap-2">
            <!-- Quick Alert for Pending Approvals -->
            <?php if ($pending_registrations > 0): ?>
                <a href="<?= BASE_URL ?>/admin/registrations.php" class="btn btn-sm btn-warning text-dark position-relative d-none d-sm-inline-flex align-items-center" title="Pending Student Registrations">
                    <i class="bi bi-person-plus-fill me-1"></i>
                    <span>Approve</span>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                        <?= $pending_registrations ?>
                    </span>
                </a>
            <?php endif; ?>

            <?php if ($pending_payments > 0): ?>
                <a href="<?= BASE_URL ?>/admin/verify_payments.php" class="btn btn-sm btn-gold position-relative d-none d-sm-inline-flex align-items-center" title="Pending Payment Verifications">
                    <i class="bi bi-cash-coin me-1"></i>
                    <span>Verify Fees</span>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                        <?= $pending_payments ?>
                    </span>
                </a>
            <?php endif; ?>

            <!-- Admin Dropdown -->
            <div class="dropdown">
                <button class="btn btn-outline-light dropdown-toggle btn-sm d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle fs-6"></i>
                    <span class="d-none d-md-inline"><?= e($_SESSION['username'] ?? 'Administrator') ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow">
                    <li><span class="dropdown-item-text small text-muted">Role: <strong>School Administrator</strong></span></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/settings.php"><i class="bi bi-gear me-2"></i>School Settings</a></li>
                    <li><a class="dropdown-item" href="<?= BASE_URL ?>/admin/audit_logs.php"><i class="bi bi-clock-history me-2"></i>Audit Logs</a></li>
                    <li><a class="dropdown-item" href="<?= BASE_URL ?>/index.php" target="_blank"><i class="bi bi-box-arrow-up-right me-2"></i>Visit Public Site</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Sign Out</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<div class="app-container">
    <!-- Admin Sidebar -->
    <aside class="sidebar collapse d-lg-block" id="adminSidebar">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= ($active_tab ?? '') === 'dashboard' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/index.php">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>

            <div class="sidebar-heading">Enrolment & Students</div>
            <li class="nav-item">
                <a class="nav-link <?= ($active_tab ?? '') === 'registrations' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/registrations.php">
                    <i class="bi bi-person-check"></i> Registration Approvals
                    <?php if ($pending_registrations > 0): ?>
                        <span class="badge bg-danger ms-auto"><?= $pending_registrations ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($active_tab ?? '') === 'students' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/students.php">
                    <i class="bi bi-mortarboard"></i> Manage Students
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($active_tab ?? '') === 'parents' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/parents.php">
                    <i class="bi bi-people"></i> Manage Parents
                </a>
            </li>

            <div class="sidebar-heading">Academic & Classes</div>
            <li class="nav-item">
                <a class="nav-link <?= ($active_tab ?? '') === 'classes' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/classes.php">
                    <i class="bi bi-diagram-3"></i> Islamiyya Classes
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($active_tab ?? '') === 'sessions' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/sessions.php">
                    <i class="bi bi-calendar3"></i> Sessions & Terms
                </a>
            </li>

            <div class="sidebar-heading">Bursary & Finances</div>
            <li class="nav-item">
                <a class="nav-link <?= ($active_tab ?? '') === 'fees' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/fees.php">
                    <i class="bi bi-tags"></i> Fee Structures
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($active_tab ?? '') === 'invoices' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/invoices.php">
                    <i class="bi bi-receipt"></i> Student Invoices
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($active_tab ?? '') === 'verify_payments' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/verify_payments.php">
                    <i class="bi bi-patch-check"></i> Payment Verification
                    <?php if ($pending_payments > 0): ?>
                        <span class="badge bg-danger ms-auto"><?= $pending_payments ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($active_tab ?? '') === 'payments' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/payments.php">
                    <i class="bi bi-credit-card"></i> All Transactions
                </a>
            </li>

            <div class="sidebar-heading">Communication & Alerts</div>
            <li class="nav-item">
                <a class="nav-link <?= ($active_tab ?? '') === 'notifications' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/notifications.php">
                    <i class="bi bi-megaphone"></i> Announcements
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($active_tab ?? '') === 'alerts' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/alerts.php">
                    <i class="bi bi-bell"></i> Alerts & Fee Debtors
                </a>
            </li>

            <div class="sidebar-heading">Analytics & Config</div>
            <li class="nav-item">
                <a class="nav-link <?= ($active_tab ?? '') === 'reports' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/reports.php">
                    <i class="bi bi-file-earmark-bar-graph"></i> Financial Reports
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($active_tab ?? '') === 'audit_logs' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/audit_logs.php">
                    <i class="bi bi-journal-text"></i> Audit Trail
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($active_tab ?? '') === 'settings' ? 'active' : '' ?>" href="<?= BASE_URL ?>/admin/settings.php">
                    <i class="bi bi-sliders"></i> School Settings
                </a>
            </li>
        </ul>
    </aside>

    <!-- Main Admin Content Area -->
    <main class="main-content">
        <div class="container-fluid p-0">
            <?= display_flash() ?>
