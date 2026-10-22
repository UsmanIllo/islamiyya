<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_parent();

$pdo = get_db_connection();
$user_id = (int)$_SESSION['user_id'];

// Handle Mark as Read action
if (isset($_GET['mark_read'])) {
    $notif_id = (int)$_GET['mark_read'];
    $stmt = $pdo->prepare("UPDATE notification_recipients SET is_read = 1, read_at = NOW() WHERE notification_id = ? AND user_id = ?");
    $stmt->execute([$notif_id, $user_id]);
    header('Location: ' . BASE_URL . '/parent/notifications.php');
    exit;
}

// Handle Mark All as Read
if (isset($_POST['mark_all_read']) && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $stmt = $pdo->prepare("UPDATE notification_recipients SET is_read = 1, read_at = NOW() WHERE user_id = ?");
    $stmt->execute([$user_id]);
    set_flash('success', 'All notifications marked as read.');
    header('Location: ' . BASE_URL . '/parent/notifications.php');
    exit;
}

$page_title = "Notifications & Announcements";
$active_tab = "notifications";
require_once __DIR__ . '/../includes/parent_header.php';

// Fetch notifications
$stmt = $pdo->prepare("
    SELECT n.*, nr.is_read, nr.read_at, u.username as sender_name
    FROM notifications n
    JOIN notification_recipients nr ON n.id = nr.notification_id
    JOIN users u ON n.sender_id = u.id
    WHERE nr.user_id = ?
    ORDER BY n.created_at DESC
");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll();
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold text-islamic mb-1">
            <i class="bi bi-bell-fill text-gold me-2"></i> Notification Centre
        </h3>
        <p class="text-muted small mb-0">Important school announcements, fee reminders, and enrolment updates.</p>
    </div>
    <?php if (!empty($notifications)): ?>
        <form method="POST" action="">
            <?= csrf_field() ?>
            <button type="submit" name="mark_all_read" value="1" class="btn btn-outline-islamic btn-sm">
                <i class="bi bi-check2-all me-1"></i> Mark All as Read
            </button>
        </form>
    <?php endif; ?>
</div>

<div class="card card-modern">
    <div class="card-body p-0">
        <?php if (empty($notifications)): ?>
            <div class="text-center py-5 text-muted">
                <i class="bi bi-bell-slash fs-1 d-block mb-2"></i>
                <h5 class="fw-bold text-islamic">No Notifications Found</h5>
                <p class="small mb-0">You have no messages or announcements at this time.</p>
            </div>
        <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($notifications as $n): ?>
                    <div class="list-group-item p-4 <?= $n['is_read'] ? 'bg-white' : 'bg-islamic-soft border-start border-4 border-success' ?>">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h5 class="fw-bold text-islamic mb-1">
                                    <?php if (!$n['is_read']): ?>
                                        <span class="badge bg-danger me-1">New</span>
                                    <?php endif; ?>
                                    <?= e($n['title']) ?>
                                </h5>
                                <div class="small text-muted">
                                    <i class="bi bi-person me-1"></i> Sent by: <?= e($n['sender_name']) ?> &bull; 
                                    <i class="bi bi-clock me-1"></i> <?= format_date($n['created_at'], 'd M Y, h:i A') ?>
                                </div>
                            </div>
                            <?php if (!$n['is_read']): ?>
                                <a href="<?= BASE_URL ?>/parent/notifications.php?mark_read=<?= $n['id'] ?>" class="btn btn-sm btn-outline-success">
                                    <i class="bi bi-check me-1"></i> Mark as Read
                                </a>
                            <?php else: ?>
                                <span class="badge bg-light text-muted border">Read</span>
                            <?php endif; ?>
                        </div>
                        <p class="mb-0 text-secondary" style="line-height: 1.6;">
                            <?= nl2br(e($n['message'])) ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/parent_footer.php'; ?>
