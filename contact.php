<?php
$page_title = "Contact Us";
$active_page = "contact";
require_once __DIR__ . '/includes/header.php';

$message_sent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if ($name && $message) {
            set_flash('success', 'Thank you! Your message has been sent to the school administration. We will reply to your contact details shortly.');
            $message_sent = true;
        } else {
            set_flash('danger', 'Please fill in all required fields.');
        }
    }
}
?>

<div class="py-5 bg-white border-bottom">
    <div class="container text-center">
        <span class="badge bg-gold text-dark px-3 py-2 rounded-pill fw-semibold">Get in Touch</span>
        <h1 class="fw-bold text-islamic mt-2">Contact & Location</h1>
        <p class="text-muted lead mx-auto" style="max-width: 650px;">
            Have questions regarding admissions, fees, or your child's Islamic progress? We are here to assist.
        </p>
    </div>
</div>

<div class="container py-5">
    <div class="row g-5">
        <div class="col-lg-5">
            <div class="card card-modern p-4 mb-4">
                <h4 class="fw-bold text-islamic mb-3">School Bursary & Inquiries</h4>
                <div class="d-flex align-items-start mb-3">
                    <div class="stat-icon bg-islamic text-white me-3" style="width: 40px; height: 40px;">
                        <i class="bi bi-geo-alt-fill"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-1">Campus Location</h6>
                        <p class="text-muted small mb-0"><?= e(get_setting('school_address')) ?></p>
                    </div>
                </div>

                <div class="d-flex align-items-start mb-3">
                    <div class="stat-icon bg-gold text-white me-3" style="width: 40px; height: 40px;">
                        <i class="bi bi-telephone-fill"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-1">Telephone Contacts</h6>
                        <p class="text-muted small mb-0"><?= e(get_setting('school_phone')) ?></p>
                    </div>
                </div>

                <div class="d-flex align-items-start mb-3">
                    <div class="stat-icon bg-islamic text-white me-3" style="width: 40px; height: 40px;">
                        <i class="bi bi-envelope-fill"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-1">Email Inquiries</h6>
                        <p class="text-muted small mb-0"><?= e(get_setting('school_email')) ?></p>
                    </div>
                </div>

                <div class="d-flex align-items-start">
                    <div class="stat-icon bg-gold text-white me-3" style="width: 40px; height: 40px;">
                        <i class="bi bi-clock-fill"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-1">Operating Hours</h6>
                        <p class="text-muted small mb-0">Monday – Thursday: 7:30 AM – 4:00 PM<br>Friday: 7:30 AM – 12:30 PM<br>Saturday & Sunday (Weekend Tahfeez): 8:00 AM – 2:00 PM</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card card-modern p-4 shadow-sm">
                <h4 class="fw-bold text-islamic mb-3"><i class="bi bi-send-fill text-gold me-2"></i> Send Us an Inquiry</h4>
                <form method="POST" action="">
                    <?= csrf_field() ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Your Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control" required placeholder="e.g. Alhaji Mustapha Sani">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Phone Number / WhatsApp <span class="text-danger">*</span></label>
                            <input type="text" name="phone" class="form-control" required placeholder="e.g. 08012345678">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Email Address (Optional)</label>
                            <input type="email" name="email" class="form-control" placeholder="e.g. parent@example.com">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Subject <span class="text-danger">*</span></label>
                            <input type="text" name="subject" class="form-control" required placeholder="e.g. Inquiry about Tahfeez Admissions">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Message <span class="text-danger">*</span></label>
                            <textarea name="message" class="form-control" rows="5" required placeholder="Please write your inquiry here..."></textarea>
                        </div>
                        <div class="col-md-12">
                            <button type="submit" class="btn btn-islamic px-4">
                                <i class="bi bi-send me-1"></i> Send Inquiry
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
