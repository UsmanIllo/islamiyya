<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Redirect if already logged in
if (is_logged_in()) {
    if (is_admin()) {
        header('Location: ' . BASE_URL . '/admin/index.php');
    } else {
        header('Location: ' . BASE_URL . '/parent/index.php');
    }
    exit;
}

$error = '';
$login_input = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Security session expired. Please refresh and try again.';
    } else {
        $login_input = trim($_POST['login_input'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($login_input) || empty($password)) {
            $error = 'Please enter your username, phone number, or email and password.';
        } else {
            $pdo = get_db_connection();
            // Search by username, phone, or email
            $stmt = $pdo->prepare("SELECT * FROM users WHERE (username = ? OR phone = ? OR email = ?) LIMIT 1");
            $stmt->execute([$login_input, $login_input, $login_input]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                if ($user['status'] === 'suspended') {
                    $error = 'Your account has been suspended by the school administration. Please contact the school office.';
                } elseif ($user['status'] === 'deactivated') {
                    $error = 'This account has been deactivated. Please contact the administration.';
                } else {
                    // Successful login
                    $_SESSION['user_id'] = (int)$user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['phone'] = $user['phone'];

                    // Update last login
                    $update = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    $update->execute([$user['id']]);

                    log_audit('USER_LOGIN', 'users', (int)$user['id'], "User {$user['username']} logged in successfully ({$user['role']}).");

                    if ($user['role'] === 'admin') {
                        set_flash('success', "Welcome back, Administrator {$user['username']}.");
                        header('Location: ' . BASE_URL . '/admin/index.php');
                    } else {
                        set_flash('success', "Assalamu Alaikum wa Rahmatullah! Welcome back to the Parent Portal.");
                        header('Location: ' . BASE_URL . '/parent/index.php');
                    }
                    exit;
                }
            } else {
                $error = 'Invalid credentials. Please verify your username/phone/email and password.';
            }
        }
    }
}

$page_title = "Portal Login";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card card-modern shadow-sm border-0">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <div class="stat-icon bg-islamic text-white mx-auto mb-3" style="width: 52px; height: 52px;">
                            <i class="bi bi-shield-lock-fill fs-3"></i>
                        </div>
                        <h4 class="fw-bold text-islamic mb-1">ISMS Portal Login</h4>
                        <p class="text-muted small">Sign in to your Administrator or Parent account</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger d-flex align-items-center small" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                            <div><?= e($error) ?></div>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <?= csrf_field() ?>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Username, Phone or Email <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-person-fill text-muted"></i></span>
                                <input type="text" name="login_input" class="form-control" value="<?= e($login_input) ?>" required placeholder="e.g. 08023456789 or admin" autofocus>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <label class="form-label fw-semibold mb-0">Password <span class="text-danger">*</span></label>
                                <a href="<?= BASE_URL ?>/auth/forgot_password.php" class="text-decoration-none small text-gold">Forgot password?</a>
                            </div>
                            <div class="input-group mt-1">
                                <span class="input-group-text bg-light"><i class="bi bi-lock-fill text-muted"></i></span>
                                <input type="password" name="password" id="login_password" class="form-control" required placeholder="Enter your password">
                                <button class="btn btn-outline-secondary password-toggle-btn" type="button" data-target="login_password" title="Show/Hide Password" tabindex="-1">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-islamic w-100 py-2 mt-2 shadow-sm">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Sign In to Portal
                        </button>
                    </form>

                    <div class="text-center mt-4 pt-3 border-top">
                        <span class="text-muted small">New parent without an account?</span>
                        <a href="<?= BASE_URL ?>/auth/register.php" class="fw-bold text-islamic text-decoration-none ms-1">Register Here</a>
                    </div>
                </div>
            </div>

            <!-- Interactive Demo Credentials Quick-Fill Cards -->
            <div class="card card-modern bg-white border mt-3 p-3 shadow-sm">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <small class="fw-bold text-islamic"><i class="bi bi-lightning-charge-fill text-gold me-1"></i> 1-Click Demo Login</small>
                    <span class="badge bg-islamic-soft text-islamic">Click to Auto-Fill</span>
                </div>
                <div class="row g-2">
                    <div class="col-6">
                        <button type="button" class="demo-cred-btn" data-role="admin">
                            <div class="d-flex align-items-center mb-1">
                                <i class="bi bi-shield-lock-fill text-danger me-1"></i>
                                <strong class="small text-dark">Administrator</strong>
                            </div>
                            <div class="text-muted" style="font-size: 0.72rem;">User: <code>admin</code></div>
                            <div class="text-muted" style="font-size: 0.72rem;">Pass: <code>Admin@123</code></div>
                        </button>
                    </div>
                    <div class="col-6">
                        <button type="button" class="demo-cred-btn" data-role="parent">
                            <div class="d-flex align-items-center mb-1">
                                <i class="bi bi-person-heart text-success me-1"></i>
                                <strong class="small text-dark">Parent Portal</strong>
                            </div>
                            <div class="text-muted" style="font-size: 0.72rem;">User: <code>parent_ibrahim</code></div>
                            <div class="text-muted" style="font-size: 0.72rem;">Pass: <code>Parent@123</code></div>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
