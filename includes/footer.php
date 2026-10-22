<?php
$school_name = get_setting('school_name', 'Darul Huda Islamic Academy');
?>
<footer class="mt-auto py-5 bg-dark text-white" style="background-color: #062b21 !important; border-top: 4px solid var(--islamic-gold);">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="d-flex align-items-center mb-3">
                    <div class="stat-icon bg-gold text-white me-2" style="width: 36px; height: 36px; font-size: 1.1rem;">
                        <i class="bi bi-book-half"></i>
                    </div>
                    <h5 class="mb-0 text-white fw-bold"><?= e($school_name) ?></h5>
                </div>
                <p class="text-white-50 small mb-3">
                    A premier Islamic institution committed to sound Islamic pedagogy, complete Quranic Tahfeez, Tajweed, Arabic literacy, and moral character based on the Quran and Sunnah.
                </p>
                <p class="text-gold small mb-0">
                    <em>"Seeking knowledge is an obligation upon every Muslim." (Ibn Majah)</em>
                </p>
            </div>
            <div class="col-lg-2 col-md-6">
                <h6 class="text-gold text-uppercase fw-bold mb-3">Quick Links</h6>
                <ul class="list-unstyled small mb-0">
                    <li class="mb-2"><a href="<?= BASE_URL ?>/index.php" class="text-white-50 text-decoration-none hover-white">Home</a></li>
                    <li class="mb-2"><a href="<?= BASE_URL ?>/about.php" class="text-white-50 text-decoration-none hover-white">About Madrasah</a></li>
                    <li class="mb-2"><a href="<?= BASE_URL ?>/admissions.php" class="text-white-50 text-decoration-none hover-white">Admissions & Fees</a></li>
                    <li class="mb-2"><a href="<?= BASE_URL ?>/contact.php" class="text-white-50 text-decoration-none hover-white">Contact & Support</a></li>
                    <li class="mb-2"><a href="<?= BASE_URL ?>/auth/login.php" class="text-white-50 text-decoration-none hover-white">Portal Login</a></li>
                </ul>
            </div>
            <div class="col-lg-3 col-md-6">
                <h6 class="text-gold text-uppercase fw-bold mb-3">Islamiyya Levels</h6>
                <ul class="list-unstyled small text-white-50 mb-0">
                    <li class="mb-1"><i class="bi bi-check2 text-gold me-1"></i> Rawdah (Islamic Nursery)</li>
                    <li class="mb-1"><i class="bi bi-check2 text-gold me-1"></i> Tahfeez Al-Quran</li>
                    <li class="mb-1"><i class="bi bi-check2 text-gold me-1"></i> Ibtida'iyyah (Primary 1-6)</li>
                    <li class="mb-1"><i class="bi bi-check2 text-gold me-1"></i> Mutawassitah (Junior Arabic)</li>
                    <li class="mb-1"><i class="bi bi-check2 text-gold me-1"></i> Thanawiyyah (Senior Arabic)</li>
                </ul>
            </div>
            <div class="col-lg-3">
                <h6 class="text-gold text-uppercase fw-bold mb-3">School Address</h6>
                <p class="small text-white-50 mb-2">
                    <i class="bi bi-geo-alt-fill text-gold me-2"></i><?= e(get_setting('school_address')) ?>
                </p>
                <p class="small text-white-50 mb-2">
                    <i class="bi bi-telephone-fill text-gold me-2"></i><?= e(get_setting('school_phone')) ?>
                </p>
                <p class="small text-white-50 mb-0">
                    <i class="bi bi-envelope-fill text-gold me-2"></i><?= e(get_setting('school_email')) ?>
                </p>
            </div>
        </div>
        <hr class="my-4 border-secondary opacity-25">
        <div class="d-flex flex-wrap justify-content-between align-items-center small text-white-50">
            <div>
                &copy; <?= date('Y') ?> <?= e($school_name) ?>. All rights reserved. (ISMS Nigeria)
            </div>
            <div class="mt-2 mt-md-0">
                <span class="text-gold">Naira Currency (₦) Supported</span> &bull; Built for Nigerian Islamiyya Schools
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap 5 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom App JS -->
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
