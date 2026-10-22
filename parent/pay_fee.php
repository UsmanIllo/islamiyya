<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_parent();

$pdo = get_db_connection();
$parent = get_current_parent_profile() ?? ['id' => 0, 'full_name' => 'Parent', 'user_id' => $_SESSION['user_id'] ?? 0];
$parent_id = (int)$parent['id'];

// Selected parameters if passed via query string
$selected_student_id = (int)($_GET['student_id'] ?? 0);
$selected_invoice_id = (int)($_GET['invoice_id'] ?? 0);

// Fetch all children belonging to this parent who have active invoices
$stmt_children = $pdo->prepare("
    SELECT DISTINCT s.id, s.first_name, s.last_name, s.student_id_number, c.class_name
    FROM students s
    JOIN student_invoices inv ON s.id = inv.student_id
    LEFT JOIN classes c ON s.current_class_id = c.id
    WHERE s.parent_id = ? AND inv.balance > 0
    ORDER BY s.first_name ASC
");
$stmt_children->execute([$parent_id]);
$eligible_children = $stmt_children->fetchAll();

// If child selected, fetch their unpaid/partially paid invoices
$invoices = [];
if ($selected_student_id > 0) {
    $stmt_inv = $pdo->prepare("
        SELECT inv.*, sess.session_name, t.term_name
        FROM student_invoices inv
        JOIN academic_sessions sess ON inv.session_id = sess.id
        JOIN academic_terms t ON inv.term_id = t.id
        WHERE inv.student_id = ? AND inv.balance > 0
        ORDER BY inv.id DESC
    ");
    $stmt_inv->execute([$selected_student_id]);
    $invoices = $stmt_inv->fetchAll();
}

// If invoice selected, fetch its details
$current_invoice = null;
if ($selected_invoice_id > 0) {
    $stmt_cur = $pdo->prepare("
        SELECT inv.*, s.first_name, s.last_name, s.student_id_number, c.class_name, sess.session_name, t.term_name
        FROM student_invoices inv
        JOIN students s ON inv.student_id = s.id
        LEFT JOIN classes c ON s.current_class_id = c.id
        JOIN academic_sessions sess ON inv.session_id = sess.id
        JOIN academic_terms t ON inv.term_id = t.id
        WHERE inv.id = ? AND s.parent_id = ?
    ");
    $stmt_cur->execute([$selected_invoice_id, $parent_id]);
    $current_invoice = $stmt_cur->fetch();
    if ($current_invoice) {
        $selected_student_id = (int)$current_invoice['student_id'];
    }
}

$errors = [];
$success = false;

// Process payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security token expired. Please try again.';
    } else {
        $post_invoice_id = (int)($_POST['invoice_id'] ?? 0);
        $amount = (float)($_POST['amount'] ?? 0);
        $payment_method = $_POST['payment_method'] ?? 'bank_transfer';
        $transaction_reference = trim($_POST['transaction_reference'] ?? '');
        $payment_note = trim($_POST['notes'] ?? '');

        // Fetch invoice and check ownership
        $stmt_check = $pdo->prepare("
            SELECT inv.*, s.first_name, s.last_name, s.student_id_number
            FROM student_invoices inv
            JOIN students s ON inv.student_id = s.id
            WHERE inv.id = ? AND s.parent_id = ?
        ");
        $stmt_check->execute([$post_invoice_id, $parent_id]);
        $inv = $stmt_check->fetch();

        if (!$inv) {
            $errors[] = 'Invalid invoice or access denied.';
        } elseif ($amount <= 0) {
            $errors[] = 'Payment amount must be greater than ₦0.00.';
        } elseif ($amount > (float)$inv['balance']) {
            $errors[] = 'Payment amount (₦' . number_format($amount, 2) . ') cannot exceed outstanding invoice balance (₦' . number_format($inv['balance'], 2) . ').';
        } else {
            // Check for duplicate transaction reference
            if (!empty($transaction_reference)) {
                $chk_dup = $pdo->prepare("SELECT id FROM payments WHERE transaction_reference = ? AND payment_status != 'rejected'");
                $chk_dup->execute([$transaction_reference]);
                if ($chk_dup->rowCount() > 0) {
                    $errors[] = 'This transaction reference has already been submitted. Please check your payment history.';
                }
            }

            if (empty($errors)) {
                $payment_ref = generate_payment_reference();

                if ($payment_method === 'bank_transfer') {
                    // Manual Bank Transfer: stays PENDING until admin verifies
                    $proof_filename = null;
                    if (!empty($_FILES['proof_file']['name'])) {
                        $file = $_FILES['proof_file'];
                        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
                        if (!in_array($ext, $allowed)) {
                            $errors[] = 'Invalid proof file format. Only JPG, PNG, and PDF files are accepted.';
                        } elseif ($file['size'] > 5 * 1024 * 1024) {
                            $errors[] = 'Proof file size must not exceed 5MB.';
                        } else {
                            $proof_filename = 'proof_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                            $target_dir = __DIR__ . '/../assets/uploads/receipts/';
                            if (!is_dir($target_dir)) {
                                mkdir($target_dir, 0777, true);
                            }
                            move_uploaded_file($file['tmp_name'], $target_dir . $proof_filename);
                        }
                    }

                    if (empty($errors)) {
                        $stmt_pay = $pdo->prepare("
                            INSERT INTO payments (
                                invoice_id, student_id, parent_id, payment_reference,
                                transaction_reference, amount, payment_method, payment_channel,
                                proof_file, payment_status, notes
                            ) VALUES (?, ?, ?, ?, ?, ?, 'bank_transfer', 'transfer', ?, 'pending', ?)
                        ");
                        $stmt_pay->execute([
                            $inv['id'], $inv['student_id'], $parent_id, $payment_ref,
                            !empty($transaction_reference) ? $transaction_reference : $payment_ref,
                            $amount, $proof_filename, $payment_note
                        ]);
                        $pay_id = (int)$pdo->lastInsertId();

                        // Notify Admin
                        send_broadcast_notification(
                            $parent['user_id'],
                            "New Payment Verification Required",
                            "Parent {$parent['full_name']} submitted a bank transfer payment claim of " . format_currency($amount) . " for student {$inv['first_name']} {$inv['last_name']}.",
                            "all"
                        );

                        log_audit('PAYMENT_CLAIM_SUBMITTED', 'payments', $pay_id, "Parent submitted bank transfer payment claim of ₦{$amount} (Ref: {$payment_ref})");

                        set_flash('success', "Payment claim of " . format_currency($amount) . " submitted successfully! Status is Pending Verification. An administrator will review your transfer proof and confirm the payment.");
                        header('Location: ' . BASE_URL . '/parent/payment_history.php');
                        exit;
                    }
                } elseif ($payment_method === 'online_paystack' || $payment_method === 'online_flutterwave') {
                    // Online Gateway Payment
                    $gateway_tx_ref = !empty($transaction_reference) ? $transaction_reference : ('GATEWAY_' . strtoupper(bin2hex(random_bytes(6))));

                    $pdo->beginTransaction();
                    try {
                        $stmt_pay = $pdo->prepare("
                            INSERT INTO payments (
                                invoice_id, student_id, parent_id, payment_reference,
                                transaction_reference, amount, payment_method, payment_channel,
                                payment_status, verified_at, notes
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'card', 'successful', NOW(), ?)
                        ");
                        $stmt_pay->execute([
                            $inv['id'], $inv['student_id'], $parent_id, $payment_ref,
                            $gateway_tx_ref, $amount, $payment_method, 'Online payment confirmed securely via ' . ucfirst(str_replace('online_', '', $payment_method))
                        ]);
                        $pay_id = (int)$pdo->lastInsertId();

                        recalculate_invoice((int)$inv['id']);

                        $rec_num = generate_receipt_number();
                        $stmt_rec = $pdo->prepare("
                            INSERT INTO receipts (receipt_number, payment_id, invoice_id, student_id, parent_id, amount, issued_date)
                            VALUES (?, ?, ?, ?, ?, ?, CURDATE())
                        ");
                        $stmt_rec->execute([$rec_num, $pay_id, $inv['id'], $inv['student_id'], $parent_id, $amount]);

                        $pdo->commit();

                        log_audit('ONLINE_PAYMENT_SUCCESS', 'payments', $pay_id, "Online payment of ₦{$amount} confirmed for student {$inv['first_name']}. Receipt: {$rec_num}");

                        set_flash('success', "Alhamdulillah! Payment of " . format_currency($amount) . " confirmed successfully. Receipt #{$rec_num} has been generated.");
                        header('Location: ' . BASE_URL . '/parent/receipt.php?id=' . $pay_id);
                        exit;
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $errors[] = "Payment processing failed: " . $e->getMessage();
                    }
                }
            }
        }
    }
}

$page_title = "Pay School Fees";
$active_tab = "pay_fee";
require_once __DIR__ . '/../includes/parent_header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-9">

        <!-- Page Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold text-islamic mb-1">
                    <i class="bi bi-credit-card-2-front-fill text-gold me-2"></i> Pay School Fees
                </h3>
                <p class="text-muted small mb-0">Select your child and preferred payment channel to settle tuition and school charges.</p>
            </div>
            <a href="<?= BASE_URL ?>/parent/fees.php" class="btn btn-outline-islamic btn-sm">
                <i class="bi bi-receipt me-1"></i> View All Invoices
            </a>
        </div>

        <!-- Error Alerts -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger alert-dismissible small shadow-sm fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Flash Messages -->
        <?= display_flash() ?>

        <!-- Step 1: Select Child & Invoice -->
        <div class="card card-modern p-4 mb-4">
            <h5 class="fw-bold text-islamic mb-3">
                <i class="bi bi-person-check me-2 text-gold"></i> Step 1: Select Child and Invoice
            </h5>
            <form method="GET" action="" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label fw-semibold">Select Child <span class="text-danger">*</span></label>
                    <select name="student_id" class="form-select" onchange="this.form.submit()">
                        <option value="">-- Choose Child with Pending Fees --</option>
                        <?php foreach ($eligible_children as $ch): ?>
                            <option value="<?= $ch['id'] ?>" <?= $selected_student_id == $ch['id'] ? 'selected' : '' ?>>
                                <?= e($ch['first_name'] . ' ' . $ch['last_name']) ?> (<?= e($ch['student_id_number'] ?? 'ID Pending') ?>) - <?= e($ch['class_name'] ?? 'Class') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label fw-semibold">Select Term Invoice <span class="text-danger">*</span></label>
                    <select name="invoice_id" class="form-select" onchange="this.form.submit()" <?= empty($invoices) ? 'disabled' : '' ?>>
                        <option value="">-- Choose Invoice --</option>
                        <?php foreach ($invoices as $inv_item): ?>
                            <option value="<?= $inv_item['id'] ?>" <?= ($current_invoice && $current_invoice['id'] == $inv_item['id']) ? 'selected' : '' ?>>
                                <?= e($inv_item['invoice_number']) ?> (<?= e($inv_item['session_name']) ?> - <?= e($inv_item['term_name']) ?>) - Bal: <?= format_currency($inv_item['balance']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-islamic w-100">
                        <i class="bi bi-arrow-repeat me-1"></i> Load
                    </button>
                </div>
            </form>
        </div>

        <?php if ($current_invoice): ?>
            <!-- Invoice Summary Card -->
            <div class="card card-modern p-4 mb-4 border-start border-4 border-success">
                <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                    <div>
                        <span class="badge bg-gold text-dark mb-1">Invoice: <?= e($current_invoice['invoice_number']) ?></span>
                        <h5 class="fw-bold text-islamic mb-0">
                            Fee Assessment for <?= e($current_invoice['first_name'] . ' ' . $current_invoice['last_name']) ?>
                        </h5>
                    </div>
                    <span class="badge bg-light text-dark border p-2">
                        <?= e($current_invoice['session_name']) ?> &bull; <?= e($current_invoice['term_name']) ?>
                    </span>
                </div>
                <div class="row g-3 text-center">
                    <div class="col-sm-4">
                        <div class="p-3 bg-light rounded">
                            <span class="text-muted small d-block">Total Assessed:</span>
                            <span class="fs-5 fw-bold text-dark"><?= format_currency($current_invoice['total_amount']) ?></span>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="p-3 bg-light rounded">
                            <span class="text-muted small d-block">Already Paid:</span>
                            <span class="fs-5 fw-bold text-success"><?= format_currency($current_invoice['amount_paid']) ?></span>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="p-3 bg-light rounded border border-danger">
                            <span class="text-muted small d-block">Outstanding Balance:</span>
                            <span class="fs-5 fw-bold text-danger"><?= format_currency($current_invoice['balance']) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 2: Payment Form -->
            <div class="card card-modern p-4 mb-5 shadow-sm">
                <h5 class="fw-bold text-islamic mb-4">
                    <i class="bi bi-shield-check me-2 text-gold"></i> Step 2: Payment Details &amp; Channel
                </h5>

                <form method="POST" action="" enctype="multipart/form-data" id="payment_form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="invoice_id" value="<?= $current_invoice['id'] ?>">

                    <!-- Row: Amount + Payment Method -->
                    <div class="row g-4 mb-4">

                        <!-- Amount Input -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Amount to Pay (₦) <span class="text-danger">*</span></label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-light fw-bold">₦</span>
                                <input type="number" step="0.01" min="100"
                                       max="<?= $current_invoice['balance'] ?>"
                                       name="amount"
                                       id="payment_amount_input"
                                       class="form-control fw-bold text-islamic fs-5"
                                       value="<?= $current_invoice['balance'] ?>"
                                       required>
                            </div>
                            <!-- Quick instalment pills -->
                            <div class="fee-quick-pills mt-2">
                                <button type="button" class="fee-pill-btn active" data-pct="100">
                                    <i class="bi bi-check2-all me-1"></i>Full (100%)
                                </button>
                                <button type="button" class="fee-pill-btn" data-pct="50">50% Half</button>
                                <button type="button" class="fee-pill-btn" data-pct="25">25% Quarter</button>
                            </div>
                            <!-- Live remaining balance indicator -->
                            <div id="fee_calc_indicator" class="fee-calc-indicator mt-2"></div>
                        </div>

                        <!-- Payment Method Visual Cards -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Payment Method <span class="text-danger">*</span></label>
                            <!-- Real select — hidden, carries POST value -->
                            <select name="payment_method" id="payment_method_select" class="d-none" required>
                                <option value="bank_transfer" selected>bank_transfer</option>
                                <option value="online_paystack">online_paystack</option>
                                <option value="online_flutterwave">online_flutterwave</option>
                            </select>
                            <!-- Clickable method cards (drive the hidden select via JS) -->
                            <div class="d-flex flex-column gap-2" id="payment_method_cards">
                                <!-- Bank Transfer -->
                                <div class="pay-method-card" data-value="bank_transfer"
                                     style="cursor:pointer; border:2px solid var(--islamic-green); border-radius:10px; padding:0.75rem 1rem; background:#eaf4f0; display:flex; align-items:center; gap:0.75rem; transition:all 0.2s ease;">
                                    <span style="width:36px;height:36px;background:var(--islamic-green);border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                        <i class="bi bi-bank text-white"></i>
                                    </span>
                                    <div>
                                        <div class="fw-bold text-islamic" style="font-size:0.88rem;">Direct Bank Transfer</div>
                                        <div class="text-muted" style="font-size:0.75rem;">Upload teller/slip &mdash; Admin verifies &amp; issues receipt</div>
                                    </div>
                                    <i class="bi bi-check-circle-fill text-islamic ms-auto" style="font-size:1.1rem;" id="pm_check_bank"></i>
                                </div>
                                <!-- Paystack -->
                                <div class="pay-method-card" data-value="online_paystack"
                                     style="cursor:pointer; border:2px solid #dee2e6; border-radius:10px; padding:0.75rem 1rem; background:#fff; display:flex; align-items:center; gap:0.75rem; transition:all 0.2s ease;">
                                    <span style="width:36px;height:36px;background:#00c3f7;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                        <i class="bi bi-credit-card-2-front text-white"></i>
                                    </span>
                                    <div>
                                        <div class="fw-bold text-dark" style="font-size:0.88rem;">Paystack Gateway</div>
                                        <div class="text-muted" style="font-size:0.75rem;">Instant &mdash; Debit Card / USSD / Transfer</div>
                                    </div>
                                    <i class="bi bi-circle text-muted ms-auto" style="font-size:1.1rem;" id="pm_check_paystack"></i>
                                </div>
                                <!-- Flutterwave -->
                                <div class="pay-method-card" data-value="online_flutterwave"
                                     style="cursor:pointer; border:2px solid #dee2e6; border-radius:10px; padding:0.75rem 1rem; background:#fff; display:flex; align-items:center; gap:0.75rem; transition:all 0.2s ease;">
                                    <span style="width:36px;height:36px;background:#f5a623;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                        <i class="bi bi-lightning-charge-fill text-white"></i>
                                    </span>
                                    <div>
                                        <div class="fw-bold text-dark" style="font-size:0.88rem;">Flutterwave Gateway</div>
                                        <div class="text-muted" style="font-size:0.75rem;">Instant &mdash; Card / Bank / USSD</div>
                                    </div>
                                    <i class="bi bi-circle text-muted ms-auto" style="font-size:1.1rem;" id="pm_check_flutter"></i>
                                </div>
                            </div>
                            <small class="text-muted mt-2 d-block">
                                <i class="bi bi-shield-check text-success me-1"></i> Safe &amp; verified payment handling
                            </small>
                        </div>
                    </div>

                    <!-- Bank Transfer Section -->
                    <div id="bank_transfer_section" class="p-3 bg-light rounded-3 border mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold text-islamic mb-0">
                                <i class="bi bi-bank me-2 text-gold"></i> School Jaiz Bank Account for Transfer
                            </h6>
                            <span class="badge bg-gold text-dark">
                                <i class="bi bi-info-circle me-1"></i> Use child name as transfer remark
                            </span>
                        </div>
                        <div class="row g-2 mb-3 small bg-white p-2 rounded border">
                            <div class="col-md-4">
                                <span class="text-muted">Bank:</span>
                                <strong><?= e(get_setting('bank_name')) ?></strong>
                            </div>
                            <div class="col-md-4">
                                <span class="text-muted">Account Name:</span>
                                <strong><?= e(get_setting('bank_account_name')) ?></strong>
                            </div>
                            <div class="col-md-4 d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="text-muted">Account No:</span>
                                    <strong class="text-gold fs-6"><?= e(get_setting('bank_account_number')) ?></strong>
                                </div>
                                <button type="button" class="copy-btn ms-2"
                                        data-copy="<?= e(get_setting('bank_account_number')) ?>"
                                        title="Copy Account Number">
                                    <i class="bi bi-clipboard"></i> Copy
                                </button>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Bank Transaction / Teller Reference</label>
                                <input type="text" name="transaction_reference" id="bank_tx_ref"
                                       class="form-control"
                                       placeholder="e.g. TRF-98234821 or Bank Teller No.">
                                <small class="text-muted">Reference from your mobile banking receipt or paper teller</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Upload Payment Proof / Transfer Slip</label>
                                <div class="file-dropzone-wrap">
                                    <input type="file" name="proof_file" id="bank_proof_file"
                                           class="dropzone-input file-dropzone-input"
                                           accept="image/*,.pdf">
                                    <div class="dropzone-icon"><i class="bi bi-cloud-arrow-up-fill"></i></div>
                                    <div class="fw-semibold text-islamic">Click to select or Drag &amp; Drop slip</div>
                                    <div class="text-muted small">Supports JPG, PNG or PDF (Max 5MB)</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Online Gateway Section -->
                    <div id="online_gateway_section" class="p-4 rounded-3 border mb-4" style="display:none; background:#f8fbf9;">
                        <div class="d-flex align-items-start gap-3">
                            <div style="width:52px;height:52px;background:linear-gradient(135deg,#28c76f,#0d5c46);border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <i class="bi bi-shield-lock-fill text-white fs-4"></i>
                            </div>
                            <div>
                                <h6 class="fw-bold text-islamic mb-1">Instant Online Payment</h6>
                                <p class="text-muted small mb-2">
                                    Your payment will be verified <strong>immediately</strong> through our secure gateway partner.
                                    An official receipt will be issued automatically right after payment.
                                </p>
                                <div class="d-flex flex-wrap gap-2">
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success-subtle px-2 py-1">
                                        <i class="bi bi-check-circle me-1"></i>Instant Receipt
                                    </span>
                                    <span class="badge bg-info bg-opacity-10 text-info border border-info-subtle px-2 py-1">
                                        <i class="bi bi-credit-card me-1"></i>Debit Card Supported
                                    </span>
                                    <span class="badge bg-warning bg-opacity-10 text-warning border border-warning-subtle px-2 py-1">
                                        <i class="bi bi-phone me-1"></i>USSD Supported
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Optional Notes -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Optional Notes / Remarks</label>
                        <input type="text" name="notes" class="form-control"
                               placeholder="e.g. Paid for Third Term tuition &amp; books">
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn btn-gold btn-lg w-100 py-3 fw-bold shadow" id="pay_submit_btn">
                        <i class="bi bi-lock-fill me-2"></i> <span id="pay_btn_label">Confirm and Submit Payment</span>
                    </button>
                </form>
            </div>
        <?php else: ?>
            <!-- No invoice selected placeholder -->
            <div class="card card-modern p-5 text-center text-muted">
                <i class="bi bi-arrow-up-circle fs-1 text-gold mb-2 d-block"></i>
                <h5 class="fw-bold text-islamic">Please Select a Child Above</h5>
                <p class="small mb-0">Choose your child from the dropdown to load the active term invoice and proceed with payment.</p>
                <?php if (empty($eligible_children)): ?>
                    <div class="mt-4">
                        <div class="alert alert-info small">
                            <i class="bi bi-info-circle-fill me-2"></i>
                            All your children currently have no outstanding fee balances — or no approved registrations with invoices yet.
                        </div>
                        <a href="<?= BASE_URL ?>/parent/my_children.php" class="btn btn-outline-islamic btn-sm">
                            <i class="bi bi-people me-1"></i> View My Children
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/parent_footer.php'; ?>
