<?php
$page_title = "Admissions & School Fees";
$active_page = "admissions";
require_once __DIR__ . '/includes/header.php';

$pdo = get_db_connection();
$cur_term = get_current_session_term();

// Fetch fee schedule for current session and term
$stmt = $pdo->prepare("
    SELECT c.class_name, c.level_category, fc.name as category_name, fs.amount
    FROM fee_structures fs
    JOIN classes c ON fs.class_id = c.id
    JOIN fee_categories fc ON fs.fee_category_id = fc.id
    WHERE fs.session_id = ? AND fs.term_id = ?
    ORDER BY c.display_order ASC, fc.id ASC
");
$stmt->execute([$cur_term['session_id'], $cur_term['term_id']]);
$fee_rows = $stmt->fetchAll();

// Group by class
$classes_fees = [];
foreach ($fee_rows as $row) {
    $cname = $row['class_name'];
    if (!isset($classes_fees[$cname])) {
        $classes_fees[$cname] = [
            'level' => $row['level_category'],
            'items' => [],
            'total' => 0.00
        ];
    }
    $classes_fees[$cname]['items'][$row['category_name']] = (float)$row['amount'];
    $classes_fees[$cname]['total'] += (float)$row['amount'];
}
?>

<div class="py-5 bg-white border-bottom">
    <div class="container text-center">
        <span class="badge bg-gold text-dark px-3 py-2 rounded-pill fw-semibold">Admission Guide</span>
        <h1 class="fw-bold text-islamic mt-2">Admissions & Approved Fee Schedule</h1>
        <p class="text-muted lead mx-auto" style="max-width: 650px;">
            Current Academic Session: <strong><?= e($cur_term['session_name']) ?> (<?= e($cur_term['term_name']) ?>)</strong>
        </p>
    </div>
</div>

<div class="container py-5">
    <!-- Step by Step Workflow -->
    <div class="mb-5">
        <div class="text-center mb-4">
            <h3 class="fw-bold text-islamic">Simple 4-Step Enrolment Procedure</h3>
            <p class="text-muted">How parents register and enrol their children online.</p>
        </div>
        <div class="row g-4">
            <div class="col-md-3">
                <div class="card h-100 stat-card p-3 text-center">
                    <div class="stat-icon bg-islamic text-white mx-auto mb-3">1</div>
                    <h6 class="fw-bold text-islamic">Parent Registration</h6>
                    <small class="text-muted">Create a parent profile with your phone, email, and home address.</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100 stat-card gold-border p-3 text-center">
                    <div class="stat-icon bg-gold text-white mx-auto mb-3">2</div>
                    <h6 class="fw-bold text-islamic">Register Children</h6>
                    <small class="text-muted">Specify how many children you wish to enrol (1, 2, 3+) and fill each form.</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100 stat-card p-3 text-center">
                    <div class="stat-icon bg-islamic text-white mx-auto mb-3">3</div>
                    <h6 class="fw-bold text-islamic">Admin Approval</h6>
                    <small class="text-muted">School administrator reviews details, assigns class, and generates a unique Student ID.</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card h-100 stat-card gold-border p-3 text-center">
                    <div class="stat-icon bg-gold text-white mx-auto mb-3">4</div>
                    <h6 class="fw-bold text-islamic">Fee Payment & Receipt</h6>
                    <small class="text-muted">Pay online or upload bank transfer slip, and print your verified receipt instantly.</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Approved Fee Table -->
    <div class="card card-modern mb-5">
        <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
            <h5 class="mb-0 text-islamic fw-bold">
                <i class="bi bi-wallet-fill text-gold me-2"></i> Termly Fee Schedule (Nigerian Naira - ₦)
            </h5>
            <span class="badge bg-islamic-soft text-islamic"><?= e($cur_term['term_name']) ?></span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Islamiyya Class / Level</th>
                            <th>Tuition Fee</th>
                            <th>Exam Fee</th>
                            <th>Books & Materials</th>
                            <th class="text-end pe-4">Total Termly Fee (₦)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($classes_fees)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">No fee structures published for this term yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($classes_fees as $cname => $info): ?>
                                <tr>
                                    <td class="ps-4 fw-bold text-islamic">
                                        <?= e($cname) ?>
                                        <div class="badge bg-light text-secondary border small"><?= e($info['level']) ?></div>
                                    </td>
                                    <td><?= format_currency($info['items']['Tuition Fee'] ?? 0) ?></td>
                                    <td><?= format_currency($info['items']['Examination & Assessment Fee'] ?? 0) ?></td>
                                    <td><?= format_currency($info['items']['Books & Learning Materials'] ?? 0) ?></td>
                                    <td class="text-end pe-4 fw-bold text-dark fs-6"><?= format_currency($info['total']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-light p-3 small text-muted">
            <i class="bi bi-info-circle me-1 text-gold"></i> Note: Instalment payments are supported. Parents may pay a minimum initial instalment or full payment through the portal.
        </div>
    </div>

    <!-- Payment Channels -->
    <div class="row g-4">
        <div class="col-md-6">
            <div class="card h-100 card-modern p-4">
                <h5 class="fw-bold text-islamic mb-3"><i class="bi bi-bank text-gold me-2"></i> Official Bank Details for Transfer</h5>
                <p class="text-muted small">You can pay via mobile banking or direct bank transfer into the school's account:</p>
                <div class="p-3 bg-light rounded-3 border">
                    <div class="row mb-2">
                        <div class="col-4 text-muted small">Bank Name:</div>
                        <div class="col-8 fw-bold text-islamic"><?= e(get_setting('bank_name')) ?></div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-4 text-muted small">Account Name:</div>
                        <div class="col-8 fw-bold"><?= e(get_setting('bank_account_name')) ?></div>
                    </div>
                    <div class="row">
                        <div class="col-4 text-muted small">Account Number:</div>
                        <div class="col-8 fw-bold text-gold fs-5"><?= e(get_setting('bank_account_number')) ?></div>
                    </div>
                </div>
                <small class="text-muted mt-2 d-block">* After making the transfer, upload your transfer screenshot or teller reference in the Parent Portal for verification.</small>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card h-100 card-modern p-4">
                <h5 class="fw-bold text-islamic mb-3"><i class="bi bi-credit-card-2-front text-gold me-2"></i> Instant Online Payment</h5>
                <p class="text-muted small">Instant payment via ATM Debit Cards, USSD, and Bank Transfer through our integrated payment gateways:</p>
                <div class="d-flex gap-3 align-items-center mb-3">
                    <span class="badge bg-success p-2 fs-6"><i class="bi bi-shield-check me-1"></i> Paystack Secured</span>
                    <span class="badge bg-primary p-2 fs-6"><i class="bi bi-shield-lock me-1"></i> Flutterwave Secured</span>
                </div>
                <p class="text-muted small">Payments made online are verified instantaneously by the system and an official receipt is issued immediately.</p>
                <a href="<?= BASE_URL ?>/auth/register.php" class="btn btn-gold mt-auto">
                    <i class="bi bi-pencil-square me-2"></i> Register and Enrol Children
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
