<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Security session expired. Please refresh and try again.';
    } else {
        $account_identifier = trim($_POST['account_identifier'] ?? '');
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($account_identifier) || empty($new_password)) {
            $error = 'Please fill in all required fields.';
        } elseif (strlen($new_password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Passwords do not match.';
        } else {
            $pdo = get_db_connection();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE phone = ? OR email = ? OR username = ?");
            $stmt->execute([$account_identifier, $account_identifier, $account_identifier]);
            $user = $stmt->fetch();

            if ($user) {
                $hash = password_hash($new_password, PASSWORD_BCRYPT);
                $update = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $update->execute([$hash, $user['id']]);

                log_audit('PASSWORD_RESET', 'users', (int)$user['id'], "Password was reset for {$user['username']}");
                $success = true;
            } else {
                $error = 'No user account found matching the provided details.';
            }
        }
    }
}

$page_title = "Reset Password";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card card-modern shadow-sm border-0">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <div class="stat-icon bg-gold text-white mx-auto mb-3" style="width: 52px; height: 52px;">
                            <i class="bi bi-key-fill fs-3"></i>
                        </div>
                        <h4 class="fw-bold text-islamic mb-1">Reset Your Password</h4>
                        <p class="text-muted small">Enter your registered phone, email, or username to set a new password</p>
                    </div>

                    <?php if ($success): ?>
                        <div class="alert alert-success small text-center" role="alert">
                            <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                            Your password has been reset successfully! You can now log in with your new credentials.
                            <div class="mt-3">
                                <a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-islamic btn-sm px-4">Go to Login</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php if ($error): ?>
                            <div class="alert alert-danger small" role="alert">
                                <?= e($error) ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <?= csrf_field() ?>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Phone Number, Email or Username <span class="text-danger">*</span></label>
                                <input type="text" name="account_identifier" class="form-control" required placeholder="e.g. 08023456789">
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">New Password <span class="text-danger">*</span></label>
                                <input type="password" name="new_password" class="form-control" required placeholder="Min. 6 characters">
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold">Confirm New Password <span class="text-danger">*</span></label>
                                <input type="password" name="confirm_password" class="form-control" required placeholder="Repeat new password">
                            </div>

                            <button type="submit" class="btn btn-islamic w-100 py-2">
                                <i class="bi bi-check2-circle me-1"></i> Update Password
                            </button>
                        </form>

                        <div class="text-center mt-4 pt-3 border-top">
                            <a href="<?= BASE_URL ?>/auth/login.php" class="text-muted small text-decoration-none">
                                <i class="bi bi-arrow-left me-1"></i> Back to Login
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
