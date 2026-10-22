<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_login();

$payment_id = (int)($_GET['id'] ?? 0);
$pdo = get_db_connection();

// Fetch payment and verify permissions
$query = "
    SELECT 
        p.*,
        r.receipt_number, r.issued_date,
        s.first_name, s.last_name, s.other_name, s.student_id_number,
        c.class_name, c.level_category,
        pt.full_name as parent_name, pt.residential_address as parent_address, u.phone as parent_phone,
        inv.invoice_number, inv.total_amount as invoice_total, inv.balance as invoice_balance, inv.amount_paid as invoice_total_paid,
        sess.session_name,
        t.term_name
    FROM payments p
    LEFT JOIN receipts r ON p.id = r.payment_id
    JOIN students s ON p.student_id = s.id
    LEFT JOIN classes c ON s.current_class_id = c.id
    JOIN parents pt ON p.parent_id = pt.id
    JOIN users u ON pt.user_id = u.id
    JOIN student_invoices inv ON p.invoice_id = inv.id
    JOIN academic_sessions sess ON inv.session_id = sess.id
    JOIN academic_terms t ON inv.term_id = t.id
    WHERE p.id = ?
";

if (is_parent()) {
    $parent = get_current_parent_profile();
    $query .= " AND p.parent_id = " . (int)$parent['id'];
}

$stmt = $pdo->prepare($query);
$stmt->execute([$payment_id]);
$rec = $stmt->fetch();

if (!$rec || $rec['payment_status'] !== 'successful') {
    die("<div style='padding:2rem; font-family:sans-serif;'><h3>Receipt Not Available</h3><p>This payment has not yet been confirmed or does not exist.</p><a href='" . BASE_URL . "'>Return to Portal</a></div>");
}

$school_name = get_setting('school_name', 'Darul Huda Islamic Academy');
$school_motto = get_setting('school_motto', 'Excellence in Quranic & Islamic Education');
$school_address = get_setting('school_address');
$school_phone = get_setting('school_phone');
$school_email = get_setting('school_email');
$footer_note = get_setting('receipt_footer_note', 'All fees paid are non-refundable. For bursary inquiries, contact the school office.');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?= e($rec['receipt_number']) ?> - <?= e($school_name) ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Amiri:wght@700&family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="bg-light py-4">

<div class="container mb-3 no-print">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 max-width-780 mx-auto" style="max-width: 780px;">
        <a href="<?= is_admin() ? BASE_URL . '/admin/payments.php' : BASE_URL . '/parent/payment_history.php' ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Back to Portal
        </a>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-islamic btn-sm copy-btn" data-copy="<?= BASE_URL ?>/parent/receipt.php?id=<?= $payment_id ?>" title="Copy public receipt verification link">
                <i class="bi bi-link-45deg me-1"></i> Copy Link
            </button>
            <button onclick="window.print()" class="btn btn-islamic btn-sm px-3 shadow-sm">
                <i class="bi bi-printer-fill me-1"></i> Print / Save as PDF
            </button>
        </div>
    </div>
</div>

<div class="receipt-container position-relative">
    <!-- Watermark -->
    <div class="receipt-watermark">
        PAID
    </div>

    <!-- Header Banner -->
    <div class="receipt-header-banner text-center position-relative" style="z-index: 1;">
        <div class="arabic-header mb-1" style="color: #0d5c46;">بِسْمِ اللَّهِ الرَّحْمَٰنِ الرَّحِيمِ</div>
        <h3 class="fw-bold text-islamic mb-0 text-uppercase"><?= e($school_name) ?></h3>
        <p class="text-gold fw-semibold small mb-1"><?= e($school_motto) ?></p>
        <p class="text-muted small mb-0">
            <?= e($school_address) ?> &bull; Tel: <?= e($school_phone) ?> &bull; Email: <?= e($school_email) ?>
        </p>
        <div class="mt-2">
            <span class="badge bg-islamic px-3 py-2 text-uppercase letter-spacing-1">Official School Fees Receipt</span>
        </div>
    </div>

    <!-- Receipt Meta Details -->
    <div class="row g-3 mb-4 position-relative" style="z-index: 1;">
        <div class="col-6">
            <table class="table table-sm table-borderless mb-0 small">
                <tr>
                    <td class="text-muted ps-0" style="width: 130px;">Receipt No:</td>
                    <td class="fw-bold text-dark font-monospace"><?= e($rec['receipt_number']) ?></td>
                </tr>
                <tr>
                    <td class="text-muted ps-0">Date Issued:</td>
                    <td class="fw-bold"><?= format_date($rec['issued_date']) ?></td>
                </tr>
                <tr>
                    <td class="text-muted ps-0">Payment Ref:</td>
                    <td class="font-monospace small"><?= e($rec['payment_reference']) ?></td>
                </tr>
                <tr>
                    <td class="text-muted ps-0">Payment Method:</td>
                    <td class="text-uppercase fw-semibold"><?= e(str_replace('_', ' ', $rec['payment_method'])) ?></td>
                </tr>
            </table>
        </div>
        <div class="col-6 text-end">
            <div class="receipt-stamp">
                <i class="bi bi-check-circle-fill me-1"></i> VERIFIED & PAID
                <div class="small fw-normal" style="font-size: 0.65rem;">BURSARY OFFICE</div>
            </div>
        </div>
    </div>

    <!-- Student & Parent Information -->
    <div class="card bg-light border-0 mb-4 p-3 position-relative" style="z-index: 1;">
        <div class="row g-2 small">
            <div class="col-md-6">
                <span class="text-muted d-block">Student Name:</span>
                <strong class="fs-6 text-islamic"><?= e($rec['first_name'] . ' ' . $rec['last_name'] . ' ' . ($rec['other_name'] ?? '')) ?></strong>
            </div>
            <div class="col-md-6">
                <span class="text-muted d-block">Student ID Number:</span>
                <strong class="fs-6 font-monospace text-dark"><?= e($rec['student_id_number'] ?? 'Pending') ?></strong>
            </div>
            <div class="col-md-6 mt-2">
                <span class="text-muted d-block">Class / Level:</span>
                <strong><?= e($rec['class_name'] ?? 'Islamiyya Level') ?></strong>
            </div>
            <div class="col-md-6 mt-2">
                <span class="text-muted d-block">Academic Period:</span>
                <strong><?= e($rec['session_name']) ?> &bull; <?= e($rec['term_name']) ?></strong>
            </div>
            <div class="col-md-12 mt-2 pt-2 border-top">
                <span class="text-muted">Parent / Guardian:</span>
                <strong><?= e($rec['parent_name']) ?></strong> (<?= e($rec['parent_phone']) ?>)
            </div>
        </div>
    </div>

    <!-- Payment Itemization Table -->
    <div class="table-responsive mb-4 position-relative" style="z-index: 1;">
        <table class="table table-bordered mb-0 small">
            <thead class="table-light">
                <tr>
                    <th>Description</th>
                    <th>Invoice No.</th>
                    <th class="text-end">Amount Paid (₦)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong>School Fees Payment (<?= e($rec['term_name']) ?>)</strong>
                        <div class="text-muted small">Session: <?= e($rec['session_name']) ?> &bull; Student: <?= e($rec['first_name']) ?></div>
                    </td>
                    <td class="font-monospace"><?= e($rec['invoice_number']) ?></td>
                    <td class="text-end fw-bold fs-6 text-islamic"><?= format_currency($rec['amount']) ?></td>
                </tr>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="2" class="text-end">Total Term Fee Assessed:</th>
                    <td class="text-end fw-bold"><?= format_currency($rec['invoice_total']) ?></td>
                </tr>
                <tr class="table-success">
                    <th colspan="2" class="text-end">Amount Paid in this Transaction:</th>
                    <td class="text-end fw-bold text-success fs-6"><?= format_currency($rec['amount']) ?></td>
                </tr>
                <tr>
                    <th colspan="2" class="text-end">Total Accumulated Payments:</th>
                    <td class="text-end fw-bold"><?= format_currency($rec['invoice_total_paid']) ?></td>
                </tr>
                <tr class="<?= $rec['invoice_balance'] > 0 ? 'table-warning' : '' ?>">
                    <th colspan="2" class="text-end">Outstanding Fee Balance:</th>
                    <td class="text-end fw-bold <?= $rec['invoice_balance'] > 0 ? 'text-danger' : 'text-success' ?>">
                        <?= format_currency($rec['invoice_balance']) ?>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Signatures & Notes -->
    <div class="row pt-4 border-top mt-4 position-relative" style="z-index: 1;">
        <div class="col-7 small text-muted">
            <p class="mb-1 fw-bold text-dark">Important Information:</p>
            <p class="mb-0" style="font-size: 0.75rem;"><?= e($footer_note) ?></p>
            <p class="mt-2 text-gold fw-semibold" style="font-size: 0.7rem;">Generated electronically via ISMS. Valid with or without stamp.</p>
        </div>
        <div class="col-5 text-center">
            <div class="border-bottom border-dark mx-auto" style="width: 180px; height: 40px;"></div>
            <span class="small text-muted d-block mt-1">Bursar / Authorized Officer</span>
            <small class="text-muted font-monospace" style="font-size: 0.65rem;"><?= date('d-m-Y H:i') ?></small>
        </div>
    </div>
</div>

</body>
</html>
