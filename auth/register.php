<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (is_logged_in()) {
    header('Location: ' . BASE_URL . (is_admin() ? '/admin/index.php' : '/parent/index.php'));
    exit;
}

$errors = [];
$full_name = '';
$phone = '';
$email = '';
$username = '';
$residential_address = '';
$emergency_contact_name = '';
$emergency_contact_phone = '';
$occupation = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security token expired. Please refresh and try again.';
    } else {
        $full_name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $residential_address = trim($_POST['residential_address'] ?? '');
        $emergency_contact_name = trim($_POST['emergency_contact_name'] ?? '');
        $emergency_contact_phone = trim($_POST['emergency_contact_phone'] ?? '');
        $occupation = trim($_POST['occupation'] ?? '');
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';

        // Validation
        if (empty($full_name)) $errors[] = 'Full Name is required.';
        if (empty($phone)) $errors[] = 'Phone number is required.';
        if (empty($residential_address)) $errors[] = 'Residential address is required.';
        if (empty($password)) $errors[] = 'Password is required.';
        if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
        if ($password !== $password_confirm) $errors[] = 'Passwords do not match.';

        // Generate username if empty
        if (empty($username)) {
            $username = 'parent_' . preg_replace('/[^a-zA-Z0-9]/', '', strtolower($phone));
        }

        $pdo = get_db_connection();

        // Check if phone or username or email already exists
        $check = $pdo->prepare("SELECT id FROM users WHERE phone = ? OR username = ?" . (!empty($email) ? " OR (email = ? AND email != '')" : ""));
        $params = [$phone, $username];
        if (!empty($email)) $params[] = $email;
        $check->execute($params);

        if ($check->rowCount() > 0) {
            $errors[] = 'An account with this phone number, username, or email already exists. Please log in instead.';
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                $hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt_user = $pdo->prepare("
                    INSERT INTO users (username, email, phone, password_hash, role, status)
                    VALUES (?, ?, ?, ?, 'parent', 'active')
                ");
                $stmt_user->execute([$username, !empty($email) ? $email : null, $phone, $hash]);
                $user_id = (int)$pdo->lastInsertId();

                $stmt_parent = $pdo->prepare("
                    INSERT INTO parents (user_id, full_name, residential_address, emergency_contact_name, emergency_contact_phone, occupation)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt_parent->execute([$user_id, $full_name, $residential_address, $emergency_contact_name, $emergency_contact_phone, $occupation]);
                $parent_id = (int)$pdo->lastInsertId();

                $pdo->commit();

                // Send welcome in-app notification
                send_broadcast_notification(1, 'Welcome to ' . get_setting('school_name'), 'Assalamu Alaikum wa Rahmatullah, esteemed parent. Your account has been registered successfully. You may now proceed to enrol your children through the "Register Child" menu.', 'selected_parent', $parent_id);

                log_audit('PARENT_REGISTERED', 'users', $user_id, "New parent account created: {$full_name} ({$username})");

                // Auto login parent
                $_SESSION['user_id'] = $user_id;
                $_SESSION['username'] = $username;
                $_SESSION['role'] = 'parent';
                $_SESSION['email'] = $email;
                $_SESSION['phone'] = $phone;

                set_flash('success', 'Account created successfully! Welcome to the Parent Portal. Please proceed to register your child/children.');
                header('Location: ' . BASE_URL . '/parent/register_child.php');
                exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = 'Registration failed: ' . $e->getMessage();
            }
        }
    }
}

$page_title = "Parent Registration";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card card-modern shadow-sm border-0">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <div class="stat-icon bg-gold text-white mx-auto mb-3" style="width: 52px; height: 52px;">
                            <i class="bi bi-person-plus-fill fs-3"></i>
                        </div>
                        <h4 class="fw-bold text-islamic mb-1">Parent Registration Form</h4>
                        <p class="text-muted small">Create your account to enrol your children, monitor Islamic progress, and pay school fees.</p>
                    </div>

                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger small">
                            <ul class="mb-0 ps-3">
                                <?php foreach ($errors as $err): ?>
                                    <li><?= e($err) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <?= csrf_field() ?>

                        <h6 class="text-islamic fw-bold border-bottom pb-2 mb-3">
                            <i class="bi bi-person me-2 text-gold"></i> Personal Information
                        </h6>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Full Name (with Title) <span class="text-danger">*</span></label>
                                <input type="text" name="full_name" class="form-control" value="<?= e($full_name) ?>" required placeholder="e.g. Alhaji Danladi Garba">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Phone Number <span class="text-danger">*</span></label>
                                <input type="tel" name="phone" class="form-control" value="<?= e($phone) ?>" required placeholder="e.g. 08031234567">
                                <small class="text-muted">Will be used for fee notices and login</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Email Address (Optional)</label>
                                <input type="email" name="email" class="form-control" value="<?= e($email) ?>" placeholder="e.g. parent@example.com">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Occupation</label>
                                <input type="text" name="occupation" class="form-control" value="<?= e($occupation) ?>" placeholder="e.g. Businessperson / Civil Servant">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-semibold">Residential Address <span class="text-danger">*</span></label>
                                <textarea name="residential_address" class="form-control" rows="2" required placeholder="House number, Street, Area/Town, State"><?= e($residential_address) ?></textarea>
                            </div>
                        </div>

                        <h6 class="text-islamic fw-bold border-bottom pb-2 mb-3">
                            <i class="bi bi-telephone-plus me-2 text-gold"></i> Emergency Contact (Next of Kin)
                        </h6>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Emergency Contact Name</label>
                                <input type="text" name="emergency_contact_name" class="form-control" value="<?= e($emergency_contact_name) ?>" placeholder="e.g. Hajiya Maryam Garba (Spouse)">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Emergency Contact Phone</label>
                                <input type="tel" name="emergency_contact_phone" class="form-control" value="<?= e($emergency_contact_phone) ?>" placeholder="e.g. 08098765432">
                            </div>
                        </div>

                        <h6 class="text-islamic fw-bold border-bottom pb-2 mb-3">
                            <i class="bi bi-shield-lock me-2 text-gold"></i> Account Security
                        </h6>

                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Username (Optional)</label>
                                <input type="text" name="username" class="form-control" value="<?= e($username) ?>" placeholder="Auto-generated if blank">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Create Password <span class="text-danger">*</span></label>
                                <input type="password" name="password" class="form-control" required placeholder="Min. 6 characters">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Confirm Password <span class="text-danger">*</span></label>
                                <input type="password" name="password_confirm" class="form-control" required placeholder="Repeat password">
                            </div>
                        </div>

                        <button type="submit" class="btn btn-islamic w-100 py-3 shadow-sm fw-bold">
                            <i class="bi bi-check-circle me-1"></i> Register Account & Proceed
                        </button>
                    </form>

                    <div class="text-center mt-4 pt-3 border-top">
                        <span class="text-muted small">Already have an account?</span>
                        <a href="<?= BASE_URL ?>/auth/login.php" class="fw-bold text-islamic text-decoration-none ms-1">Login Here</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
