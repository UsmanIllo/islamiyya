<?php
$page_title = "Audit Logs";
$active_tab = "audit_logs";
require_once __DIR__ . '/../includes/admin_header.php';

$pdo = get_db_connection();

$search = trim($_GET['search'] ?? '');
$sql = "
    SELECT a.*, u.username, u.role
    FROM audit_logs a
    LEFT JOIN users u ON a.user_id = u.id
    WHERE 1=1
";
$params = [];
if (!empty($search)) {
    $sql .= " AND (a.action LIKE ? OR a.description LIKE ? OR u.username LIKE ? OR a.ip_address LIKE ?)";
    $term = "%{$search}%";
    $params = [$term, $term, $term, $term];
}
$sql .= " ORDER BY a.id DESC LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold text-islamic mb-1">
            <i class="bi bi-journal-text text-gold me-2"></i> System Audit Trail
        </h3>
        <p class="text-muted small mb-0">Immutable tracking of user logins, financial transactions, approvals, and administrative actions.</p>
    </div>
</div>

<div class="card card-modern p-3 mb-4">
    <form method="GET" action="" class="row g-2 align-items-center">
        <div class="col-md-9">
            <div class="input-group">
                <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                <input type="text" name="search" class="form-control" value="<?= e($search) ?>" placeholder="Search action, description, username, IP address...">
            </div>
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-islamic w-100">Filter Audit Trail</button>
        </div>
    </form>
</div>

<div class="card card-modern">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h5 class="mb-0 text-islamic fw-bold">Audit Entries (<?= count($logs) ?>)</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4" style="width: 180px;">Timestamp</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Entity</th>
                        <th>Description</th>
                        <th class="text-end pe-4">IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">No audit logs found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $l): ?>
                            <tr>
                                <td class="ps-4 font-monospace text-muted"><?= format_date($l['created_at'], 'd M Y, H:i:s') ?></td>
                                <td>
                                    <?php if ($l['username']): ?>
                                        <strong><?= e($l['username']) ?></strong>
                                        <span class="badge bg-light text-muted border"><?= e($l['role']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">System</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-islamic-soft text-islamic font-monospace"><?= e($l['action']) ?></span>
                                </td>
                                <td>
                                    <span class="text-muted"><?= e($l['entity_type']) ?><?= $l['entity_id'] ? ' #' . $l['entity_id'] : '' ?></span>
                                </td>
                                <td class="text-secondary"><?= e($l['description']) ?></td>
                                <td class="text-end pe-4 font-monospace text-muted"><?= e($l['ip_address'] ?? '127.0.0.1') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
