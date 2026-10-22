<?php
$page_title = "My Profile";
$active_tab = "profile";
require_once __DIR__ . '/../includes/parent_header.php';

$pdo = get_db_connection();
$parent_id = (int)$parent['id'];
$user_id = (int)$_SESSION['user_id'];

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security token expired. Please try again.';
    } else {
        $full_name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $occupation = trim($_POST['occupation'] ?? '');
        $residential_address = trim($_POST['residential_address'] ?? '');
        $emergency_contact_name = trim($_POST['emergency_contact_name'] ?? '');
        $emergency_contact_phone = trim($_POST['emergency_contact_phone'] ?? '');

        if (empty($full_name)) $errors[] = 'Full name is required.';
        if (empty($phone)) $errors[] = 'Phone number is required.';
        if (empty($residential_address)) $errors[] = 'Residential address is required.';

        // Check if phone is taken by another user
        $chk = $pdo->prepare("SELECT id FROM users WHERE phone = ? AND id != ?");
        $chk->execute([$phone, $user_id]);
        if ($chk->rowCount() > 0) {
            $errors[] = 'Another account is already using this phone number.';
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                $stmt1 = $pdo->prepare("UPDATE users SET phone = ?, email = ? WHERE id = ?");
                $stmt1->execute([$phone, !empty($email) ? $email : null, $user_id]);

                $stmt2 = $pdo->prepare("
                    UPDATE parents 
                    SET full_name = ?, occupation = ?, residential_address = ?, emergency_contact_name = ?, emergency_contact_phone = ?
                    WHERE id = ?
                ");
                $stmt2->execute([$full_name, $occupation, $residential_address, $emergency_contact_name, $emergency_contact_phone, $parent_id]);

                $pdo->commit();

                // Refresh parent session variables
                $_SESSION['phone'] = $phone;
                $_SESSION['email'] = $email;

                log_audit('PROFILE_UPDATED', 'parents', $parent_id, "Parent updated profile info.");

                set_flash('success', 'Profile information updated successfully.');
                header('Location: ' . BASE_URL . '/parent/profile.php');
                exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = "Update failed: " . $e->getMessage();
            }
        }
    }
}
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold text-islamic mb-1">
                    <i class="bi bi-person-lines-fill text-gold me-2"></i> Parent Profile & Contact Details
                </h3>
                <p class="text-muted small mb-0">Manage your contact records and emergency information.</p>
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

        <div class="card card-modern p-4 p-md-5">
            <form method="POST" action="">
                <?= csrf_field() ?>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" class="form-control" value="<?= e($parent['full_name']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Phone Number <span class="text-danger">*</span></label>
                        <input type="tel" name="phone" class="form-control" value="<?= e($parent['phone']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Email Address</label>
                        <input type="email" name="email" class="form-control" value="<?= e($parent['email']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Occupation</label>
                        <input type="text" name="occupation" class="form-control" value="<?= e($parent['occupation']) ?>">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label fw-semibold">Residential Address <span class="text-danger">*</span></label>
                        <textarea name="residential_address" class="form-control" rows="2" required><?= e($parent['residential_address']) ?></textarea>
                    </div>
                </div>

                <h6 class="text-islamic fw-bold border-bottom pb-2 mb-3">
                    <i class="bi bi-telephone-plus text-gold me-2"></i> Emergency Contact (Next of Kin)
                </h6>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Emergency Contact Person</label>
                        <input type="text" name="emergency_contact_name" class="form-control" value="<?= e($parent['emergency_contact_name']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Emergency Contact Phone</label>
                        <input type="tel" name="emergency_contact_phone" class="form-control" value="<?= e($parent['emergency_contact_phone']) ?>">
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                    <a href="<?= BASE_URL ?>/parent/change_password.php" class="text-gold text-decoration-none small fw-semibold">
                        <i class="bi bi-key me-1"></i> Change Password
                    </a>
                    <button type="submit" class="btn btn-islamic px-4">
                        <i class="bi bi-save me-1"></i> Save Profile Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/parent_footer.php'; ?>
