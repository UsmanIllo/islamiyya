<?php
$page_title = "About Our Islamiyya";
$active_page = "about";
require_once __DIR__ . '/includes/header.php';
$school_name = get_setting('school_name', 'Darul Huda Islamic Academy');
?>

<div class="py-5 bg-white border-bottom">
    <div class="container text-center">
        <span class="badge bg-gold text-dark px-3 py-2 rounded-pill fw-semibold">About Us</span>
        <h1 class="fw-bold text-islamic mt-2"><?= e($school_name) ?></h1>
        <p class="text-muted lead mx-auto" style="max-width: 650px;">
            Cultivating the next generation of pious, knowledgeable, and morally upright Muslim youth in Nigeria.
        </p>
    </div>
</div>

<div class="container py-5">
    <div class="row g-5 align-items-center mb-5">
        <div class="col-lg-6">
            <span class="badge bg-islamic-soft text-islamic mb-2">Our Vision & Mission</span>
            <h2 class="fw-bold text-islamic mb-3">Excellence in Quranic & Arabic Studies</h2>
            <p class="text-secondary">
                Founded with the primary mission of addressing the spiritual, linguistic, and moral needs of Muslim children in Nigeria, <?= e($school_name) ?> blends traditional Islamic scholarship with modern educational management.
            </p>
            <p class="text-secondary">
                Our curriculum encompasses Tahfeezul Quran (Quran memorization), Tajweed, Duroosul Lughah (Arabic Language), Hadith, Fiqh, Tawheed, and Islamic Ethics (Adaab). We believe every child has a unique learning pace, and our teachers are trained to nurture them with patience, compassion, and discipline.
            </p>
            <div class="row g-3 mt-2">
                <div class="col-sm-6">
                    <div class="p-3 border rounded bg-white stat-card">
                        <i class="bi bi-shield-check text-gold fs-4 mb-2 d-block"></i>
                        <h6 class="fw-bold text-islamic">Certified Instructors</h6>
                        <small class="text-muted">Qualified Huffaz and Arabic language scholars.</small>
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="p-3 border rounded bg-white stat-card gold-border">
                        <i class="bi bi-cash-stack text-islamic fs-4 mb-2 d-block"></i>
                        <h6 class="fw-bold text-islamic">Transparent Fees</h6>
                        <small class="text-muted">Flexible instalment options in Naira (₦) with instant receipts.</small>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card card-modern p-4 border-0 shadow-sm bg-islamic-soft">
                <h4 class="fw-bold text-islamic mb-3"><i class="bi bi-journal-bookmark me-2 text-gold"></i> Structure of Classes</h4>
                <ul class="list-group list-group-flush bg-transparent">
                    <li class="list-group-item bg-transparent px-0 py-3">
                        <strong class="text-islamic">1. Rawdah (Islamic Kindergarten)</strong>
                        <div class="text-muted small">Phonetics, Qa'idah Nooraniyyah, basic Dua'a, and introductory Quran recitation.</div>
                    </li>
                    <li class="list-group-item bg-transparent px-0 py-3">
                        <strong class="text-islamic">2. Ibtida'iyyah (Primary Level 1 - 6)</strong>
                        <div class="text-muted small">Surah memorization, Arabic vocabulary, basic Fiqh of Salah and Taharah, Seerah.</div>
                    </li>
                    <li class="list-group-item bg-transparent px-0 py-3">
                        <strong class="text-islamic">3. Mutawassitah (Junior Islamic Studies 1 - 3)</strong>
                        <div class="text-muted small">Arabic Grammar (Nahw and Sarf), Fiqh Al-Muyassar, Hadith memorization with commentary.</div>
                    </li>
                    <li class="list-group-item bg-transparent px-0 py-3">
                        <strong class="text-islamic">4. Thanawiyyah (Senior Islamic Studies 1 - 3)</strong>
                        <div class="text-muted small">Tafseerul Quran, advanced Arabic prose, Usul Al-Fiqh, Islamic history and civilization.</div>
                    </li>
                    <li class="list-group-item bg-transparent px-0 py-3">
                        <strong class="text-islamic">5. Dedicated Tahfeez Track</strong>
                        <div class="text-muted small">Intensive full memorization of the Holy Quran with Tajweed certification.</div>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
