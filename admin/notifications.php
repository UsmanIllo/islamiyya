<?php
$page_title = "Manage Notifications & Announcements";
$active_tab = "notifications";
require_once __DIR__ . '/../includes/admin_header.php';

$pdo = get_db_connection();

$classes = $pdo->query("SELECT id, class_name, level_category FROM classes WHERE status = 'active' ORDER BY display_order ASC")->fetchAll();
$parents = $pdo->query("SELECT id, full_name, user_id FROM parents ORDER BY full_name ASC")->fetchAll();

$pre_selected_parent_id = (int)($_GET['target_parent_id'] ?? 0);

// Handle Send Notification POST
if (isset($_POST['send_notification']) && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $title = trim($_POST['title']);
    $message = trim($_POST['message']);
    $target_type = $_POST['target_type']; // 'all', 'selected_parent', 'class'
    $target_parent_id = !empty($_POST['target_parent_id']) ? (int)$_POST['target_parent_id'] : null;
    $target_class_id = !empty($_POST['target_class_id']) ? (int)$_POST['target_class_id'] : null;

    if (!empty($title) && !empty($message)) {
        $notif_id = send_broadcast_notification(
            (int)$_SESSION['user_id'],
            $title,
            $message,
            $target_type,
            $target_parent_id,
            $target_class_id
        );

        log_audit('NOTIFICATION_DISPATCHED', 'notifications', $notif_id, "Sent notification: {$title} (Target: {$target_type})");
        set_flash('success', "Notification dispatched successfully to target recipients.");
        header('Location: ' . BASE_URL . '/admin/notifications.php');
        exit;
    } else {
        set_flash('danger', 'Please provide a title and message.');
    }
}

// Fetch notification history with recipient counts and read counts
$stmt_history = $pdo->query("
    SELECT 
        n.*, 
        u.username as sender_name,
        c.class_name as target_class_name,
        pt.full_name as target_parent_name,
        COUNT(nr.id) as total_recipients,
        SUM(CASE WHEN nr.is_read = 1 THEN 1 ELSE 0 END) as total_read
    FROM notifications n
    JOIN users u ON n.sender_id = u.id
    LEFT JOIN classes c ON n.target_class_id = c.id
    LEFT JOIN parents pt ON n.target_parent_id = pt.id
    LEFT JOIN notification_recipients nr ON n.id = nr.notification_id
    GROUP BY n.id
    ORDER BY n.id DESC
");
$history = $stmt_history->fetchAll();
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold text-islamic mb-1">
            <i class="bi bi-megaphone-fill text-gold me-2"></i> Manage Announcements & Notifications
        </h3>
        <p class="text-muted small mb-0">Broadcast school-wide circulars, term dates, fee deadlines, or class-specific messages to parents.</p>
    </div>
</div>

<div class="row g-4">
    <!-- Compose Form -->
    <div class="col-lg-5">
        <div class="card card-modern p-4 shadow-sm border-start border-4 border-warning">
            <h5 class="fw-bold text-islamic mb-3">
                <i class="bi bi-pencil-square text-gold me-2"></i> Compose Announcement
            </h5>

            <form method="POST" action="">
                <?= csrf_field() ?>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Target Audience <span class="text-danger">*</span></label>
                    <select name="target_type" id="target_type_select" class="form-select" required>
                        <option value="all" <?= $pre_selected_parent_id == 0 ? 'selected' : '' ?>>All Registered Parents (Broadcast)</option>
                        <option value="selected_parent" <?= $pre_selected_parent_id > 0 ? 'selected' : '' ?>>Specific Parent</option>
                        <option value="class">Parents of a Specific Class / Level</option>
                    </select>
                </div>

                <!-- Specific Parent Selector -->
                <div class="mb-3" id="parent_select_div" style="<?= $pre_selected_parent_id > 0 ? '' : 'display: none;' ?>">
                    <label class="form-label fw-semibold">Select Parent</label>
                    <select name="target_parent_id" class="form-select">
                        <option value="">-- Choose Parent --</option>
                        <?php foreach ($parents as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= $pre_selected_parent_id == $p['id'] ? 'selected' : '' ?>>
                                <?= e($p['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Specific Class Selector -->
                <div class="mb-3" id="class_select_div" style="display: none;">
                    <label class="form-label fw-semibold">Select Class</label>
                    <select name="target_class_id" class="form-select">
                        <option value="">-- Choose Islamiyya Class --</option>
                        <?php foreach ($classes as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= e($c['class_name']) ?> (<?= e($c['level_category']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Announcement Title / Subject <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" required placeholder="e.g. School Reopening Date & Fee Reminders">
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold">Message Body <span class="text-danger">*</span></label>
                    <textarea name="message" class="form-control" rows="5" required placeholder="Write the announcement or message here..."></textarea>
                    <small class="text-muted">Will appear directly in the parents' notification inbox.</small>
                </div>

                <button type="submit" name="send_notification" value="1" class="btn btn-islamic w-100 py-2 fw-semibold">
                    <i class="bi bi-send-fill me-1"></i> Send Announcement
                </button>
            </form>
        </div>
    </div>

    <!-- History List -->
    <div class="col-lg-7">
        <div class="card card-modern">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <h5 class="mb-0 text-islamic fw-bold">Dispatched Messages (<?= count($history) ?>)</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($history)): ?>
                    <p class="text-center py-5 text-muted">No notifications sent yet.</p>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($history as $h): ?>
                            <div class="list-group-item p-4">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <div>
                                        <h5 class="fw-bold text-islamic mb-1"><?= e($h['title']) ?></h5>
                                        <div class="small text-muted">
                                            <i class="bi bi-clock me-1"></i> <?= format_date($h['created_at'], 'd M Y, h:i A') ?> &bull; 
                                            Target: 
                                            <?php if ($h['target_type'] === 'all'): ?>
                                                <span class="badge bg-success">All Parents</span>
                                            <?php elseif ($h['target_type'] === 'class'): ?>
                                                <span class="badge bg-primary">Class: <?= e($h['target_class_name']) ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-gold text-dark"><?= e($h['target_parent_name']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-light text-dark border">
                                            <?= $h['total_read'] ?> / <?= $h['total_recipients'] ?> Read
                                        </span>
                                    </div>
                                </div>
                                <p class="mb-0 text-secondary small" style="line-height: 1.6;">
                                    <?= nl2br(e($h['message'])) ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const select = document.getElementById('target_type_select');
    const pdiv = document.getElementById('parent_select_div');
    const cdiv = document.getElementById('class_select_div');
    if (select) {
        select.addEventListener('change', function() {
            if (this.value === 'selected_parent') {
                pdiv.style.display = 'block';
                cdiv.style.display = 'none';
            } else if (this.value === 'class') {
                pdiv.style.display = 'none';
                cdiv.style.display = 'block';
            } else {
                pdiv.style.display = 'none';
                cdiv.style.display = 'none';
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
