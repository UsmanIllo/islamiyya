<?php
require_once __DIR__ . '/auth.php';
require_parent();

$school_name = get_setting('school_name', 'Darul Huda Islamic Academy');
$parent = get_current_parent_profile() ?? [
    'id' => 0,
    'full_name' => $_SESSION['username'] ?? 'Parent',
    'phone' => $_SESSION['phone'] ?? '',
    'email' => $_SESSION['email'] ?? '',
    'residential_address' => '',
    'occupation' => '',
    'emergency_contact_name' => '',
    'emergency_contact_phone' => ''
];
$unread_notifs = get_unread_notification_count($_SESSION['user_id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? e($page_title) . " | " : "" ?>Parent Portal - <?= e($school_name) ?></title>
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
        <button class="btn btn-outline-light d-lg-none me-2" type="button" data-bs-toggle="collapse" data-bs-target="#parentSidebar">
            <i class="bi bi-list fs-5"></i>
        </button>
        <a class="navbar-brand d-flex align-items-center" href="<?= BASE_URL ?>/parent/index.php">
            <div class="stat-icon bg-gold text-white me-2" style="width: 38px; height: 38px; font-size: 1.2rem;">
                <i class="bi bi-person-heart"></i>
            </div>
            <div>
                <span class="navbar-brand-text d-block lh-1 fs-6"><?= e($school_name) ?></span>
                <span class="navbar-brand-subtitle">Parent Portal</span>
            </div>
        </a>

        <div class="ms-auto d-flex align-items-center gap-2">
            <!-- Notifications Bell -->
            <a href="<?= BASE_URL ?>/parent/notifications.php" class="btn btn-outline-light btn-sm position-relative" title="Notifications">
                <i class="bi bi-bell-fill"></i>
                <?php if ($unread_notifs > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                        <?= $unread_notifs ?>
                    </span>
                <?php endif; ?>
            </a>

            <!-- Quick Register Child CTA -->
            <a href="<?= BASE_URL ?>/parent/register_child.php" class="btn btn-gold btn-sm d-none d-sm-inline-flex align-items-center">
                <i class="bi bi-person-plus-fill me-1"></i> Register Child
            </a>

            <!-- Parent Profile Dropdown -->
            <div class="dropdown">
                <button class="btn btn-outline-light dropdown-toggle btn-sm d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle fs-6"></i>
                    <span class="d-none d-md-inline"><?= e($parent['full_name'] ?? $_SESSION['username']) ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow">
                    <li><span class="dropdown-item-text small text-muted">Logged in as <strong>Parent</strong></span></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="<?= BASE_URL ?>/parent/profile.php"><i class="bi bi-person me-2"></i>My Profile</a></li>
                    <li><a class="dropdown-item" href="<?= BASE_URL ?>/parent/change_password.php"><i class="bi bi-key me-2"></i>Change Password</a></li>
                    <li><a class="dropdown-item" href="<?= BASE_URL ?>/index.php" target="_blank"><i class="bi bi-house me-2"></i>Public Homepage</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?= BASE_URL ?>/auth/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Sign Out</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<div class="app-container">
    <!-- Parent Sidebar -->
    <aside class="sidebar collapse d-lg-block" id="parentSidebar">
        <div class="p-3 mb-2 bg-islamic-soft rounded-3 text-center">
            <div class="fw-bold text-islamic mb-1"><?= e($parent['full_name'] ?? 'Respected Parent') ?></div>
            <div class="badge bg-gold small">Parent Account</div>
        </div>

        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?= ($active_tab ?? '') === 'dashboard' ? 'active' : '' ?>" href="<?= BASE_URL ?>/parent/index.php">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>

            <div class="sidebar-heading">My Family</div>
            <li class="nav-item">
                <a class="nav-link <?= ($active_tab ?? '') === 'my_children' ? 'active' : '' ?>" href="<?= BASE_URL ?>/parent/my_children.php">
                    <i class="bi bi-people"></i> My Children
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($active_tab ?? '') === 'register_child' ? 'active' : '' ?>" href="<?= BASE_URL ?>/parent/register_child.php">
                    <i class="bi bi-person-plus"></i> Register a Child
                </a>
            </li>

            <div class="sidebar-heading">Fees & Payments</div>
            <li class="nav-item">
                <a class="nav-link <?= ($active_tab ?? '') === 'fees' ? 'active' : '' ?>" href="<?= BASE_URL ?>/parent/fees.php">
                    <i class="bi bi-wallet2"></i> School Fees
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($active_tab ?? '') === 'pay_fee' ? 'active' : '' ?>" href="<?= BASE_URL ?>/parent/pay_fee.php">
                    <i class="bi bi-credit-card"></i> Pay School Fees
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($active_tab ?? '') === 'payment_history' ? 'active' : '' ?>" href="<?= BASE_URL ?>/parent/payment_history.php">
                    <i class="bi bi-clock-history"></i> Payment History
                </a>
            </li>

            <div class="sidebar-heading">Communication</div>
            <li class="nav-item">
                <a class="nav-link <?= ($active_tab ?? '') === 'notifications' ? 'active' : '' ?>" href="<?= BASE_URL ?>/parent/notifications.php">
                    <i class="bi bi-bell"></i> Notifications
                    <?php if ($unread_notifs > 0): ?>
                        <span class="badge bg-danger ms-auto"><?= $unread_notifs ?></span>
                    <?php endif; ?>
                </a>
            </li>

            <div class="sidebar-heading">Account Settings</div>
            <li class="nav-item">
                <a class="nav-link <?= ($active_tab ?? '') === 'profile' ? 'active' : '' ?>" href="<?= BASE_URL ?>/parent/profile.php">
                    <i class="bi bi-person-lines-fill"></i> My Profile
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= ($active_tab ?? '') === 'change_password' ? 'active' : '' ?>" href="<?= BASE_URL ?>/parent/change_password.php">
                    <i class="bi bi-shield-lock"></i> Change Password
                </a>
            </li>
        </ul>
    </aside>

    <!-- Main Parent Content Area -->
    <main class="main-content">
        <div class="container-fluid p-0">
            <?= display_flash() ?>
