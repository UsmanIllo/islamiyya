<?php
$page_title = "Manage Parents";
$active_tab = "parents";
require_once __DIR__ . '/../includes/admin_header.php';

$pdo = get_db_connection();

// Status toggle action (activate, suspend, deactivate)
if (isset($_POST['change_status']) && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $target_user_id = (int)$_POST['user_id'];
    $new_status = $_POST['new_status'];
    if (in_array($new_status, ['active', 'suspended', 'deactivated'])) {
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ? AND role = 'parent'");
        $stmt->execute([$new_status, $target_user_id]);
        log_audit('PARENT_STATUS_CHANGE', 'users', $target_user_id, "Changed parent status to {$new_status}");
        set_flash('success', "Parent account status updated to " . ucfirst($new_status));
        header('Location: ' . BASE_URL . '/admin/parents.php');
        exit;
    }
}

// Reset Password action
if (isset($_POST['reset_password']) && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $target_user_id = (int)$_POST['user_id'];
    $new_password = $_POST['new_password'] ?? 'Parent@123';
    if (strlen($new_password) >= 6) {
        $hash = password_hash($new_password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ? AND role = 'parent'");
        $stmt->execute([$hash, $target_user_id]);
        log_audit('PARENT_PASSWORD_RESET', 'users', $target_user_id, "Admin reset parent password.");
        set_flash('success', "Password reset successfully. New password: {$new_password}");
        header('Location: ' . BASE_URL . '/admin/parents.php');
        exit;
    } else {
        set_flash('danger', 'Password must be at least 6 characters.');
    }
}

// Fetch parents with child count and total fees paid
$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';

$sql = "
    SELECT 
        p.*, u.username, u.email, u.phone, u.status, u.last_login,
        COUNT(DISTINCT s.id) as children_count,
        COALESCE(SUM(pay.amount), 0) as total_paid
    FROM parents p
    JOIN users u ON p.user_id = u.id
    LEFT JOIN students s ON s.parent_id = p.id
    LEFT JOIN payments pay ON pay.parent_id = p.id AND pay.payment_status = 'successful'
    WHERE 1=1
";
$params = [];
if (!empty($search)) {
    $sql .= " AND (p.full_name LIKE ? OR u.phone LIKE ? OR u.email LIKE ? OR u.username LIKE ?)";
    $term = "%{$search}%";
    $params = [$term, $term, $term, $term];
}
if (!empty($status_filter) && in_array($status_filter, ['active', 'suspended', 'deactivated'])) {
    $sql .= " AND u.status = ?";
    $params[] = $status_filter;
}
$sql .= " GROUP BY p.id ORDER BY p.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$parents = $stmt->fetchAll();
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold text-islamic mb-1">
            <i class="bi bi-people-fill text-gold me-2"></i> Manage Parents
        </h3>
        <p class="text-muted small mb-0">View registered parents, monitor associated children, and manage account statuses.</p>
    </div>
</div>

<!-- Search & Filter Card -->
<div class="card card-modern p-3 mb-4">
    <form method="GET" action="" class="row g-2 align-items-center">
        <div class="col-md-5">
            <div class="input-group">
                <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                <input type="text" name="search" class="form-control" value="<?= e($search) ?>" placeholder="Search by name, phone, email, or username...">
            </div>
        </div>
        <div class="col-md-3">
            <select name="status" class="form-select">
                <option value="">All Account Statuses</option>
                <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="suspended" <?= $status_filter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                <option value="deactivated" <?= $status_filter === 'deactivated' ? 'selected' : '' ?>>Deactivated</option>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-islamic w-100">Filter</button>
        </div>
        <div class="col-md-2">
            <a href="<?= BASE_URL ?>/admin/parents.php" class="btn btn-outline-secondary w-100">Reset</a>
        </div>
    </form>
</div>

<!-- Parents Table -->
<div class="card card-modern">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h5 class="mb-0 text-islamic fw-bold">
            Registered Parents (<?= count($parents) ?>)
        </h5>
        <input type="text" class="form-control form-control-sm table-search-input" data-target-table="parents_table" placeholder="Instant filter..." style="width: 200px;">
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="parents_table">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Parent Full Name</th>
                        <th>Phone / Username</th>
                        <th>Email</th>
                        <th>Children</th>
                        <th>Total Paid (₦)</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($parents)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">No parent accounts found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($parents as $p): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-islamic"><?= e($p['full_name']) ?></div>
                                    <small class="text-muted"><?= e($p['residential_address']) ?></small>
                                </td>
                                <td>
                                    <a href="tel:<?= e($p['phone']) ?>" class="fw-semibold text-dark text-decoration-none"><?= e($p['phone']) ?></a>
                                    <div class="small text-muted">@<?= e($p['username']) ?></div>
                                </td>
                                <td>
                                    <?= !empty($p['email']) ? e($p['email']) : '<span class="text-muted small">None</span>' ?>
                                </td>
                                <td>
                                    <span class="badge bg-islamic-soft text-islamic fw-bold fs-6">
                                        <?= $p['children_count'] ?> Child(ren)
                                    </span>
                                </td>
                                <td class="fw-bold text-success"><?= format_currency($p['total_paid']) ?></td>
                                <td>
                                    <?php if ($p['status'] === 'active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php elseif ($p['status'] === 'suspended'): ?>
                                        <span class="badge bg-warning text-dark">Suspended</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Deactivated</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="dropdown d-inline-block">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                            Options
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow">
                                            <li>
                                                <a class="dropdown-item" href="<?= BASE_URL ?>/admin/parent_view.php?id=<?= $p['id'] ?>">
                                                    <i class="bi bi-eye me-2 text-islamic"></i> View Full Profile
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#resetPassModal<?= $p['id'] ?>">
                                                    <i class="bi bi-key me-2 text-gold"></i> Reset Password
                                                </a>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <?php if ($p['status'] === 'active'): ?>
                                                <li>
                                                    <form method="POST" action="" onsubmit="return confirmAction('Suspend parent account?')">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="user_id" value="<?= $p['user_id'] ?>">
                                                        <input type="hidden" name="new_status" value="suspended">
                                                        <button type="submit" name="change_status" value="1" class="dropdown-item text-warning">
                                                            <i class="bi bi-pause-circle me-2"></i> Suspend Account
                                                        </button>
                                                    </form>
                                                </li>
                                                <li>
                                                    <form method="POST" action="" onsubmit="return confirmAction('Deactivate parent account?')">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="user_id" value="<?= $p['user_id'] ?>">
                                                        <input type="hidden" name="new_status" value="deactivated">
                                                        <button type="submit" name="change_status" value="1" class="dropdown-item text-danger">
                                                            <i class="bi bi-slash-circle me-2"></i> Deactivate Account
                                                        </button>
                                                    </form>
                                                </li>
                                            <?php else: ?>
                                                <li>
                                                    <form method="POST" action="" onsubmit="return confirmAction('Activate parent account?')">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="user_id" value="<?= $p['user_id'] ?>">
                                                        <input type="hidden" name="new_status" value="active">
                                                        <button type="submit" name="change_status" value="1" class="dropdown-item text-success">
                                                            <i class="bi bi-check-circle me-2"></i> Activate Account
                                                        </button>
                                                    </form>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </td>
                            </tr>

                            <!-- Reset Password Modal -->
                            <div class="modal fade" id="resetPassModal<?= $p['id'] ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content text-start">
                                        <form method="POST" action="">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="user_id" value="<?= $p['user_id'] ?>">

                                            <div class="modal-header bg-gold text-white">
                                                <h5 class="modal-title fw-bold">
                                                    <i class="bi bi-key-fill me-2"></i> Reset Parent Password
                                                </h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body p-4">
                                                <p class="small text-muted mb-3">
                                                    Set a temporary or new password for <strong><?= e($p['full_name']) ?></strong> (<?= e($p['phone']) ?>).
                                                </p>
                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold">New Password <span class="text-danger">*</span></label>
                                                    <input type="text" name="new_password" class="form-control" value="Parent@<?= rand(100, 999) ?>" required>
                                                    <small class="text-muted">You can communicate this new password to the parent securely.</small>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" name="reset_password" value="1" class="btn btn-gold btn-sm px-4">
                                                    Save New Password
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
