<?php
$page_title = "My Children";
$active_tab = "my_children";
require_once __DIR__ . '/../includes/parent_header.php';

$pdo = get_db_connection();
$parent_id = (int)$parent['id'];

$stmt = $pdo->prepare("
    SELECT s.*, c.class_name as current_class_name, rc.class_name as requested_class_name,
        COALESCE(SUM(inv.balance), 0) as total_debt
    FROM students s
    LEFT JOIN classes c ON s.current_class_id = c.id
    LEFT JOIN classes rc ON s.requested_class_id = rc.id
    LEFT JOIN student_invoices inv ON s.id = inv.student_id
    WHERE s.parent_id = ?
    GROUP BY s.id
    ORDER BY s.id DESC
");
$stmt->execute([$parent_id]);
$children = $stmt->fetchAll();
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold text-islamic mb-1">
            <i class="bi bi-people-fill text-gold me-2"></i> My Enrolled Children
        </h3>
        <p class="text-muted small mb-0">Overview of all children registered under your parent account.</p>
    </div>
    <a href="<?= BASE_URL ?>/parent/register_child.php" class="btn btn-gold shadow-sm">
        <i class="bi bi-person-plus-fill me-1"></i> Add Another Child
    </a>
</div>

<?php if (empty($children)): ?>
    <div class="card card-modern p-5 text-center">
        <i class="bi bi-person-x text-muted" style="font-size: 3.5rem;"></i>
        <h4 class="fw-bold text-islamic mt-3">No Children Registered Yet</h4>
        <p class="text-muted mx-auto mb-4" style="max-width: 500px;">
            Enrol your children to begin their Islamic journey, track curriculum milestones, and manage school fees.
        </p>
        <div>
            <a href="<?= BASE_URL ?>/parent/register_child.php" class="btn btn-islamic px-4">
                <i class="bi bi-pencil-square me-1"></i> Register a Child Now
            </a>
        </div>
    </div>
<?php else: ?>
    <div class="row g-4">
        <?php foreach ($children as $child): ?>
            <?php 
                $age = date_diff(date_create($child['date_of_birth']), date_create('today'))->y;
            ?>
            <div class="col-md-6 col-lg-4">
                <div class="card card-modern h-100">
                    <div class="card-body p-4 d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <span class="badge bg-islamic-soft text-islamic mb-1">
                                    <i class="bi bi-<?= $child['gender'] === 'Male' ? 'gender-male' : 'gender-female' ?> me-1"></i>
                                    <?= e($child['gender']) ?> &bull; <?= $age ?> yrs
                                </span>
                                <h5 class="fw-bold text-islamic mb-0">
                                    <?= e($child['first_name'] . ' ' . $child['last_name']) ?>
                                </h5>
                                <?php if (!empty($child['other_name'])): ?>
                                    <small class="text-muted"><?= e($child['other_name']) ?></small>
                                <?php endif; ?>
                            </div>
                            <div class="stat-icon bg-light text-islamic" style="width: 44px; height: 44px;">
                                <i class="bi bi-mortarboard-fill fs-5"></i>
                            </div>
                        </div>

                        <div class="p-3 bg-light rounded-3 mb-3 small">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="text-muted">Student ID:</span>
                                <div>
                                    <?php if (!empty($child['student_id_number'])): ?>
                                        <strong class="text-islamic"><?= e($child['student_id_number']) ?></strong>
                                        <button type="button" class="copy-btn py-0 px-2 ms-1" data-copy="<?= e($child['student_id_number']) ?>" title="Copy Student ID">
                                            <i class="bi bi-clipboard"></i>
                                        </button>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i> Pending Approval</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">Class / Level:</span>
                                <strong><?= e($child['current_class_name'] ?? $child['requested_class_name']) ?></strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Fee Balance:</span>
                                <strong class="<?= $child['total_debt'] > 0 ? 'text-danger' : 'text-success' ?>">
                                    <?= format_currency($child['total_debt']) ?>
                                </strong>
                            </div>
                        </div>

                        <!-- Registration Status Pill -->
                        <div class="mb-3">
                            <?php if ($child['registration_status'] === 'approved'): ?>
                                <div class="alert alert-success py-2 px-3 small mb-0 d-flex align-items-center">
                                    <i class="bi bi-check-circle-fill me-2 fs-6"></i>
                                    <div><strong>Approved & Enrolled</strong></div>
                                </div>
                            <?php elseif ($child['registration_status'] === 'rejected'): ?>
                                <div class="alert alert-danger py-2 px-3 small mb-0">
                                    <i class="bi bi-exclamation-octagon-fill me-1"></i> <strong>Registration Rejected</strong>
                                    <?php if (!empty($child['rejection_reason'])): ?>
                                        <div class="mt-1 small">Reason: <?= e($child['rejection_reason']) ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning py-2 px-3 small mb-0 d-flex align-items-center">
                                    <i class="bi bi-hourglass-split me-2 fs-6"></i>
                                    <div><strong>Pending Review by School Admin</strong></div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="mt-auto d-flex gap-2">
                            <a href="<?= BASE_URL ?>/parent/child_details.php?id=<?= $child['id'] ?>" class="btn btn-outline-islamic btn-sm flex-fill">
                                View Profile & Fees
                            </a>
                            <?php if ($child['total_debt'] > 0): ?>
                                <a href="<?= BASE_URL ?>/parent/pay_fee.php?student_id=<?= $child['id'] ?>" class="btn btn-gold btn-sm px-3" title="Pay Fees">
                                    <i class="bi bi-credit-card"></i> Pay
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/parent_footer.php'; ?>
