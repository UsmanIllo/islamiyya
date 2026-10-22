<?php
$page_title = "School Settings";
$active_tab = "settings";
require_once __DIR__ . '/../includes/admin_header.php';

$pdo = get_db_connection();

// Save Settings POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $settings_to_update = [
        'school_name' => trim($_POST['school_name'] ?? ''),
        'school_motto' => trim($_POST['school_motto'] ?? ''),
        'school_address' => trim($_POST['school_address'] ?? ''),
        'school_phone' => trim($_POST['school_phone'] ?? ''),
        'school_email' => trim($_POST['school_email'] ?? ''),
        'currency_symbol' => trim($_POST['currency_symbol'] ?? '₦'),
        'bank_name' => trim($_POST['bank_name'] ?? ''),
        'bank_account_name' => trim($_POST['bank_account_name'] ?? ''),
        'bank_account_number' => trim($_POST['bank_account_number'] ?? ''),
        'paystack_public_key' => trim($_POST['paystack_public_key'] ?? ''),
        'paystack_secret_key' => trim($_POST['paystack_secret_key'] ?? ''),
        'paystack_enabled' => isset($_POST['paystack_enabled']) ? '1' : '0',
        'flutterwave_public_key' => trim($_POST['flutterwave_public_key'] ?? ''),
        'flutterwave_secret_key' => trim($_POST['flutterwave_secret_key'] ?? ''),
        'flutterwave_enabled' => isset($_POST['flutterwave_enabled']) ? '1' : '0',
        'receipt_footer_note' => trim($_POST['receipt_footer_note'] ?? '')
    ];

    try {
        $stmt_up = $pdo->prepare("
            INSERT INTO school_settings (setting_key, setting_value) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
        ");
        foreach ($settings_to_update as $key => $val) {
            $stmt_up->execute([$key, $val]);
        }

        log_audit('SETTINGS_UPDATED', 'school_settings', null, "Admin updated school settings.");
        set_flash('success', "School settings updated successfully.");
        header('Location: ' . BASE_URL . '/admin/settings.php');
        exit;
    } catch (Exception $e) {
        set_flash('danger', "Error saving settings: " . $e->getMessage());
    }
}

$s = get_school_settings();
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold text-islamic mb-1">
            <i class="bi bi-sliders text-gold me-2"></i> School Settings
        </h3>
        <p class="text-muted small mb-0">Configure school profile details, bank accounts, payment gateway credentials, and receipt text.</p>
    </div>
</div>

<form method="POST" action="">
    <?= csrf_field() ?>

    <div class="row g-4">
        <!-- School General Information -->
        <div class="col-lg-6">
            <div class="card card-modern p-4 h-100">
                <h5 class="fw-bold text-islamic mb-3 border-bottom pb-2">
                    <i class="bi bi-building me-2 text-gold"></i> School Profile Details
                </h5>

                <div class="mb-3">
                    <label class="form-label fw-semibold">School Name <span class="text-danger">*</span></label>
                    <input type="text" name="school_name" class="form-control" value="<?= e($s['school_name'] ?? '') ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">School Motto / Tagline</label>
                    <input type="text" name="school_motto" class="form-control" value="<?= e($s['school_motto'] ?? '') ?>">
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Campus Address <span class="text-danger">*</span></label>
                    <textarea name="school_address" class="form-control" rows="2" required><?= e($s['school_address'] ?? '') ?></textarea>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Phone Number(s) <span class="text-danger">*</span></label>
                        <input type="text" name="school_phone" class="form-control" value="<?= e($s['school_phone'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                        <input type="email" name="school_email" class="form-control" value="<?= e($s['school_email'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Currency Symbol (Nigeria)</label>
                    <input type="text" name="currency_symbol" class="form-control" value="<?= e($s['currency_symbol'] ?? '₦') ?>" required style="max-width: 100px;">
                </div>
            </div>
        </div>

        <!-- Official Bank Account Details -->
        <div class="col-lg-6">
            <div class="card card-modern p-4 mb-4">
                <h5 class="fw-bold text-islamic mb-3 border-bottom pb-2">
                    <i class="bi bi-bank me-2 text-gold"></i> Official Bank Details (Transfer Payments)
                </h5>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Bank Name <span class="text-danger">*</span></label>
                    <input type="text" name="bank_name" class="form-control" value="<?= e($s['bank_name'] ?? '') ?>" required placeholder="e.g. Jaiz Bank PLC">
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Account Name <span class="text-danger">*</span></label>
                    <input type="text" name="bank_account_name" class="form-control" value="<?= e($s['bank_account_name'] ?? '') ?>" required>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Account Number <span class="text-danger">*</span></label>
                    <input type="text" name="bank_account_number" class="form-control fw-bold font-monospace fs-5 text-islamic" value="<?= e($s['bank_account_number'] ?? '') ?>" required placeholder="e.g. 0012345678">
                </div>
            </div>

            <!-- Receipt Custom Note -->
            <div class="card card-modern p-4">
                <h5 class="fw-bold text-islamic mb-3 border-bottom pb-2">
                    <i class="bi bi-printer me-2 text-gold"></i> Receipt Notice
                </h5>
                <div class="mb-2">
                    <label class="form-label fw-semibold">Footer Disclaimer on Receipts</label>
                    <textarea name="receipt_footer_note" class="form-control" rows="2"><?= e($s['receipt_footer_note'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- Payment Gateway API Keys -->
        <div class="col-12">
            <div class="card card-modern p-4">
                <h5 class="fw-bold text-islamic mb-3 border-bottom pb-2">
                    <i class="bi bi-shield-lock me-2 text-gold"></i> Payment Gateways Configuration
                </h5>
                <p class="text-muted small">Enter your live or test credentials for Paystack and Flutterwave to accept card and USSD payments.</p>

                <div class="row g-4">
                    <!-- Paystack -->
                    <div class="col-md-6 border-end">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold text-success mb-0"><i class="bi bi-credit-card me-1"></i> Paystack Settings</h6>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="paystack_enabled" id="paystack_switch" <?= !empty($s['paystack_enabled']) ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="paystack_switch">Enable Paystack</label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Paystack Public Key</label>
                            <input type="text" name="paystack_public_key" class="form-control form-control-sm font-monospace" value="<?= e($s['paystack_public_key'] ?? '') ?>" placeholder="pk_test_...">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Paystack Secret Key</label>
                            <input type="password" name="paystack_secret_key" class="form-control form-control-sm font-monospace" value="<?= e($s['paystack_secret_key'] ?? '') ?>" placeholder="sk_test_...">
                        </div>
                    </div>

                    <!-- Flutterwave -->
                    <div class="col-md-6">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold text-primary mb-0"><i class="bi bi-shield-check me-1"></i> Flutterwave Settings</h6>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="flutterwave_enabled" id="flw_switch" <?= !empty($s['flutterwave_enabled']) ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="flw_switch">Enable Flutterwave</label>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Flutterwave Public Key</label>
                            <input type="text" name="flutterwave_public_key" class="form-control form-control-sm font-monospace" value="<?= e($s['flutterwave_public_key'] ?? '') ?>" placeholder="FLWPUBK_TEST-...">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold">Flutterwave Secret Key</label>
                            <input type="password" name="flutterwave_secret_key" class="form-control form-control-sm font-monospace" value="<?= e($s['flutterwave_secret_key'] ?? '') ?>" placeholder="FLWSECK_TEST-...">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 text-end mb-5">
            <button type="submit" class="btn btn-islamic btn-lg px-5 shadow">
                <i class="bi bi-check-circle me-1"></i> Save All School Settings
            </button>
        </div>
    </div>
</form>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
