<?php
$page_title = "Change Password";
$active_tab = "change_password";
require_once __DIR__ . '/../includes/parent_header.php';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security token expired. Please try again.';
    } else {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($current_password) || empty($new_password)) {
            $errors[] = 'Please fill in all password fields.';
        } elseif (strlen($new_password) < 6) {
            $errors[] = 'New password must be at least 6 characters.';
        } elseif ($new_password !== $confirm_password) {
            $errors[] = 'New passwords do not match.';
        } else {
            $pdo = get_db_connection();
            $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $current_hash = $stmt->fetchColumn();

            if (!password_verify($current_password, $current_hash)) {
                $errors[] = 'The current password you entered is incorrect.';
            } else {
                $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
                $update = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $update->execute([$new_hash, $_SESSION['user_id']]);

                log_audit('PASSWORD_CHANGED', 'users', (int)$_SESSION['user_id'], "User changed password.");

                set_flash('success', 'Your password has been updated successfully.');
                header('Location: ' . BASE_URL . '/parent/profile.php');
                exit;
            }
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold text-islamic mb-1">
                    <i class="bi bi-shield-lock-fill text-gold me-2"></i> Change Password
                </h3>
                <p class="text-muted small mb-0">Update your parent account security password.</p>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger small shadow-sm">
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card card-modern p-4">
            <form method="POST" action="">
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Current Password <span class="text-danger">*</span></label>
                    <input type="password" name="current_password" class="form-control" required placeholder="Enter existing password">
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">New Password <span class="text-danger">*</span></label>
                    <input type="password" name="new_password" class="form-control" required placeholder="Min. 6 characters">
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold">Confirm New Password <span class="text-danger">*</span></label>
                    <input type="password" name="confirm_password" class="form-control" required placeholder="Repeat new password">
                </div>

                <button type="submit" class="btn btn-islamic w-100 py-2 fw-semibold">
                    <i class="bi bi-check2-circle me-1"></i> Update Password
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/parent_footer.php'; ?>
