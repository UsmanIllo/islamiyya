<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
$school_name = get_setting('school_name', 'Darul Huda Islamic Academy');
$school_motto = get_setting('school_motto', 'Excellence in Quranic & Islamic Education');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? e($page_title) . " | " : "" ?><?= e($school_name) ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Google Fonts Arabic & Modern -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Amiri:wght@400;700&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom Theme -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>

<!-- Public Top Bar -->
<div class="bg-dark text-white py-1 px-3 d-none d-md-block" style="background-color: #083e2f !important; font-size: 0.82rem; border-bottom: 1px solid rgba(197,155,39,0.3);">
    <div class="container d-flex justify-content-between align-items-center">
        <div>
            <i class="bi bi-telephone-fill text-gold me-1"></i> <?= e(get_setting('school_phone')) ?>
            <span class="mx-2 text-white-50">|</span>
            <i class="bi bi-envelope-fill text-gold me-1"></i> <?= e(get_setting('school_email')) ?>
        </div>
        <div>
            <span class="arabic-header d-inline me-2" style="font-size: 1.1rem;">بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ</span>
            <span class="badge bg-gold ms-2">ISMS Nigeria</span>
        </div>
    </div>
</div>

<!-- Main Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark navbar-islamic sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center" href="<?= BASE_URL ?>/index.php">
            <div class="stat-icon bg-gold text-white me-2" style="width: 42px; height: 42px; font-size: 1.3rem;">
                <i class="bi bi-book-half"></i>
            </div>
            <div>
                <span class="navbar-brand-text d-block lh-1 fs-5"><?= e($school_name) ?></span>
                <span class="navbar-brand-subtitle"><?= e($school_motto) ?></span>
            </div>
        </a>
        <button class="navbar-toggler" type="button" data-bs-display="static" data-bs-toggle="collapse" data-bs-target="#navbarMain">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-lg-center">
                <li class="nav-item">
                    <a class="nav-link <?= ($active_page ?? '') === 'home' ? 'active fw-bold' : '' ?>" href="<?= BASE_URL ?>/index.php">
                        <i class="bi bi-house-door me-1"></i> Home
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($active_page ?? '') === 'about' ? 'active fw-bold' : '' ?>" href="<?= BASE_URL ?>/about.php">About</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($active_page ?? '') === 'admissions' ? 'active fw-bold' : '' ?>" href="<?= BASE_URL ?>/admissions.php">Admissions & Fees</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($active_page ?? '') === 'contact' ? 'active fw-bold' : '' ?>" href="<?= BASE_URL ?>/contact.php">Contact</a>
                </li>

                <?php if (is_logged_in()): ?>
                    <?php if (is_admin()): ?>
                        <li class="nav-item ms-lg-2">
                            <a class="btn btn-gold btn-sm" href="<?= BASE_URL ?>/admin/index.php">
                                <i class="bi bi-speedometer2 me-1"></i> Admin Dashboard
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item ms-lg-2">
                            <a class="btn btn-gold btn-sm" href="<?= BASE_URL ?>/parent/index.php">
                                <i class="bi bi-person-circle me-1"></i> Parent Portal
                            </a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item ms-lg-1">
                        <a class="nav-link text-warning" href="<?= BASE_URL ?>/auth/logout.php" title="Sign Out">
                            <i class="bi bi-box-arrow-right"></i>
                        </a>
                    </li>
                <?php else: ?>
                    <li class="nav-item ms-lg-2">
                        <a class="btn btn-outline-light btn-sm px-3" href="<?= BASE_URL ?>/auth/login.php">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Login
                        </a>
                    </li>
                    <li class="nav-item ms-lg-2 mt-2 mt-lg-0">
                        <a class="btn btn-gold btn-sm px-3" href="<?= BASE_URL ?>/auth/register.php">
                            <i class="bi bi-pencil-square me-1"></i> Parent Register
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<!-- Main Page Flash Alerts -->
<div class="container mt-3">
    <?= display_flash() ?>
</div>
