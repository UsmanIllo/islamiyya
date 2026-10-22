<?php
$page_title = "Home";
$active_page = "home";
require_once __DIR__ . '/includes/header.php';

$pdo = get_db_connection();
$cur_term = get_current_session_term();

// Fetch public announcements
$stmt = $pdo->query("SELECT * FROM notifications WHERE target_type = 'all' ORDER BY created_at DESC LIMIT 3");
$announcements = $stmt->fetchAll();

// Fetch classes
$stmt_classes = $pdo->query("SELECT * FROM classes WHERE status = 'active' ORDER BY display_order ASC LIMIT 6");
$classes = $stmt_classes->fetchAll();
?>

<!-- Hero Banner -->
<section class="hero-section text-center position-relative">
    <div class="container py-4">
        <div class="arabic-header">بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ</div>
        <div class="badge bg-gold text-dark px-3 py-2 mb-3 fw-semibold rounded-pill">
            <i class="bi bi-mortarboard-fill me-1"></i> Academic Session <?= e($cur_term['session_name']) ?> &bull; <?= e($cur_term['term_name']) ?>
        </div>
        <h1 class="display-4 fw-bold text-white mb-3">
            Nurturing Righteous Generations with <span class="text-gold">Quran & Wisdom</span>
        </h1>
        <p class="lead text-white-50 mx-auto mb-4" style="max-width: 750px;">
            <?= e(get_setting('school_name')) ?> provides authentic Islamic education, Quranic Tahfeez with Tajweed, classical Arabic literacy, and Islamic character for children from early years to senior level.
        </p>
        <div class="d-flex flex-wrap justify-content-center gap-3">
            <a href="<?= BASE_URL ?>/auth/register.php" class="btn btn-gold btn-lg px-4 shadow">
                <i class="bi bi-pencil-square me-2"></i> Register as a Parent
            </a>
            <a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-outline-light btn-lg px-4">
                <i class="bi bi-box-arrow-in-right me-2"></i> Portal Login
            </a>
            <a href="<?= BASE_URL ?>/admissions.php" class="btn btn-islamic btn-lg px-4 border border-light">
                <i class="bi bi-info-circle me-2"></i> View School Fees
            </a>
        </div>
    </div>
</section>

<!-- Core Pillars -->
<section class="py-5 bg-white">
    <div class="container">
        <div class="text-center mb-5">
            <span class="badge bg-islamic-soft text-islamic px-3 py-2 rounded-pill fw-semibold">Our Pillars</span>
            <h2 class="fw-bold text-islamic mt-2">Authentic Islamic Curriculum</h2>
            <p class="text-muted mx-auto" style="max-width: 600px;">Rooted in the Quran and the Sunnah upon the understanding of the righteous predecessors.</p>
        </div>

        <div class="row g-4">
            <div class="col-md-4">
                <div class="card h-100 stat-card p-4">
                    <div class="stat-icon bg-islamic text-white mb-3">
                        <i class="bi bi-book"></i>
                    </div>
                    <h5 class="fw-bold text-islamic">Quran Tahfeez & Tajweed</h5>
                    <p class="text-muted small mb-0">Structured memorization tracks, phonetic Qa'idah Nooraniyyah, and rules of Tajweed with qualified Hafiz instructors.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 stat-card gold-border p-4">
                    <div class="stat-icon bg-gold text-white mb-3">
                        <i class="bi bi-translate"></i>
                    </div>
                    <h5 class="fw-bold text-islamic">Arabic Language Literacy</h5>
                    <p class="text-muted small mb-0">Conversational Arabic, reading fluency, grammar (Nahw & Sarf), and vocabulary using reputable standard textbooks.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card h-100 stat-card p-4">
                    <div class="stat-icon bg-islamic text-white mb-3">
                        <i class="bi bi-heart-pulse"></i>
                    </div>
                    <h5 class="fw-bold text-islamic">Tawheed, Fiqh & Adaab</h5>
                    <p class="text-muted small mb-0">Essential Islamic monotheism, practical Salah and Taharah demonstrations, prophetic manners, and daily Adhkaar.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Islamiyya Levels & Fee Transparency -->
<section class="py-5" style="background-color: var(--islamic-bg);">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-end mb-4">
            <div>
                <span class="badge bg-gold text-dark px-3 py-2 rounded-pill fw-semibold">Levels of Study</span>
                <h2 class="fw-bold text-islamic mt-2 mb-0">Classes Offered at <?= e(get_setting('school_name')) ?></h2>
            </div>
            <a href="<?= BASE_URL ?>/admissions.php" class="btn btn-outline-islamic mt-3 mt-md-0">
                View All Fees & Requirements <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>

        <div class="row g-4">
            <?php foreach ($classes as $cls): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 card-modern">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge bg-islamic-soft text-islamic fw-bold"><?= e($cls['level_category']) ?></span>
                                <i class="bi bi-check-circle-fill text-gold"></i>
                            </div>
                            <h5 class="card-title fw-bold text-islamic mb-2"><?= e($cls['class_name']) ?></h5>
                            <p class="card-text text-muted small"><?= e($cls['description'] ?? 'Complete Islamic and Arabic foundational classes for enrolled students.') ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- Latest School Announcements -->
<?php if (!empty($announcements)): ?>
<section class="py-5 bg-white">
    <div class="container">
        <div class="text-center mb-5">
            <span class="badge bg-danger px-3 py-2 rounded-pill fw-semibold">Notices</span>
            <h2 class="fw-bold text-islamic mt-2">Latest School Announcements</h2>
        </div>

        <div class="row g-4 justify-content-center">
            <?php foreach ($announcements as $ann): ?>
                <div class="col-lg-6">
                    <div class="card card-modern border-start border-4 border-success h-100">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-muted small"><i class="bi bi-calendar3 me-1"></i> <?= format_date($ann['created_at']) ?></span>
                                <span class="badge bg-islamic-soft text-islamic">Official Notice</span>
                            </div>
                            <h5 class="fw-bold text-islamic mb-2"><?= e($ann['title']) ?></h5>
                            <p class="text-secondary small mb-0"><?= nl2br(e($ann['message'])) ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- Call To Action -->
<section class="py-5 text-white text-center" style="background: linear-gradient(135deg, var(--islamic-green) 0%, var(--islamic-green-dark) 100%);">
    <div class="container">
        <h2 class="fw-bold mb-3">Enrol Your Children in an Authentic Islamiyya Today</h2>
        <p class="lead text-white-50 mx-auto mb-4" style="max-width: 650px;">
            Parents can register online, add multiple children to their account, track academic fees in Nigerian Naira, and pay conveniently via Paystack or direct bank transfer.
        </p>
        <a href="<?= BASE_URL ?>/auth/register.php" class="btn btn-gold btn-lg px-5 shadow">
            <i class="bi bi-person-plus-fill me-2"></i> Register New Parent Account
        </a>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
