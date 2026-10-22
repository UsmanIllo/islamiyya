<?php
$page_title = "Manage Classes & Levels";
$active_tab = "classes";
require_once __DIR__ . '/../includes/admin_header.php';

$pdo = get_db_connection();

// Handle Add Class POST
if (isset($_POST['add_class']) && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $class_name = trim($_POST['class_name']);
    $level_category = trim($_POST['level_category']);
    $description = trim($_POST['description'] ?? '');
    $display_order = (int)($_POST['display_order'] ?? 1);

    if (!empty($class_name) && !empty($level_category)) {
        $stmt = $pdo->prepare("INSERT INTO classes (class_name, level_category, description, display_order, status) VALUES (?, ?, ?, ?, 'active')");
        $stmt->execute([$class_name, $level_category, $description, $display_order]);
        log_audit('CLASS_CREATED', 'classes', (int)$pdo->lastInsertId(), "Created class {$class_name}");
        set_flash('success', "Islamiyya class '{$class_name}' added successfully.");
        header('Location: ' . BASE_URL . '/admin/classes.php');
        exit;
    }
}

// Handle Edit Class POST
if (isset($_POST['edit_class']) && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $class_id = (int)$_POST['class_id'];
    $class_name = trim($_POST['class_name']);
    $level_category = trim($_POST['level_category']);
    $description = trim($_POST['description'] ?? '');
    $display_order = (int)$_POST['display_order'];
    $status = $_POST['status'];

    $stmt = $pdo->prepare("UPDATE classes SET class_name = ?, level_category = ?, description = ?, display_order = ?, status = ? WHERE id = ?");
    $stmt->execute([$class_name, $level_category, $description, $display_order, $status, $class_id]);
    log_audit('CLASS_UPDATED', 'classes', $class_id, "Updated class {$class_name}");
    set_flash('success', "Class '{$class_name}' updated successfully.");
    header('Location: ' . BASE_URL . '/admin/classes.php');
    exit;
}

// Fetch all classes with student counts
$stmt = $pdo->query("
    SELECT c.*, COUNT(s.id) as enrolled_count
    FROM classes c
    LEFT JOIN students s ON s.current_class_id = c.id AND s.enrolment_status = 'active'
    GROUP BY c.id
    ORDER BY c.display_order ASC
");
$classes = $stmt->fetchAll();
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold text-islamic mb-1">
            <i class="bi bi-diagram-3-fill text-gold me-2"></i> Islamiyya Classes & Levels
        </h3>
        <p class="text-muted small mb-0">Configure academic streams, Quranic Tahfeez tracks, and Arabic grade levels.</p>
    </div>
    <button type="button" class="btn btn-gold btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#addClassModal">
        <i class="bi bi-plus-circle me-1"></i> Add New Class / Level
    </button>
</div>

<div class="card card-modern">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4" style="width: 60px;">Order</th>
                        <th>Class Name</th>
                        <th>Category / Level</th>
                        <th>Description / Curriculum Focus</th>
                        <th>Enrolled Students</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($classes)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">No classes configured.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($classes as $c): ?>
                            <tr>
                                <td class="ps-4 fw-bold text-muted"><?= $c['display_order'] ?></td>
                                <td>
                                    <div class="fw-bold text-islamic fs-6"><?= e($c['class_name']) ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-gold text-dark"><?= e($c['level_category']) ?></span>
                                </td>
                                <td>
                                    <small class="text-secondary"><?= e($c['description'] ?? 'Standard Islamiyya curriculum') ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-islamic-soft text-islamic fs-6">
                                        <?= $c['enrolled_count'] ?> Students
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $c['status'] === 'active' ? 'success' : 'secondary' ?>">
                                        <?= ucfirst($c['status']) ?>
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <button type="button" class="btn btn-sm btn-outline-islamic" data-bs-toggle="modal" data-bs-target="#editClassModal<?= $c['id'] ?>">
                                        <i class="bi bi-pencil-square me-1"></i> Edit
                                    </button>
                                </td>
                            </tr>

                            <!-- Edit Class Modal -->
                            <div class="modal fade" id="editClassModal<?= $c['id'] ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content text-start">
                                        <form method="POST" action="">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="class_id" value="<?= $c['id'] ?>">

                                            <div class="modal-header bg-islamic text-white">
                                                <h5 class="modal-title fw-bold">Edit Class Details</h5>
                                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body p-4">
                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold">Class Name <span class="text-danger">*</span></label>
                                                    <input type="text" name="class_name" class="form-control" value="<?= e($c['class_name']) ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold">Level Category <span class="text-danger">*</span></label>
                                                    <select name="level_category" class="form-select" required>
                                                        <option value="Rawdah" <?= $c['level_category'] === 'Rawdah' ? 'selected' : '' ?>>Rawdah (Early Childhood / Kindergarten)</option>
                                                        <option value="Tahfeez" <?= $c['level_category'] === 'Tahfeez' ? 'selected' : '' ?>>Tahfeez (Quran Memorization Track)</option>
                                                        <option value="Ibtida'iyyah" <?= $c['level_category'] === "Ibtida'iyyah" ? 'selected' : '' ?>>Ibtida'iyyah (Primary Level)</option>
                                                        <option value="Mutawassitah" <?= $c['level_category'] === 'Mutawassitah' ? 'selected' : '' ?>>Mutawassitah (Junior Islamic Studies)</option>
                                                        <option value="Thanawiyyah" <?= $c['level_category'] === 'Thanawiyyah' ? 'selected' : '' ?>>Thanawiyyah (Senior Islamic Studies)</option>
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold">Display Order</label>
                                                    <input type="number" name="display_order" class="form-control" value="<?= $c['display_order'] ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold">Status</label>
                                                    <select name="status" class="form-select">
                                                        <option value="active" <?= $c['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                                        <option value="inactive" <?= $c['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label fw-semibold">Curriculum Focus / Description</label>
                                                    <textarea name="description" class="form-control" rows="2"><?= e($c['description']) ?></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" name="edit_class" value="1" class="btn btn-islamic btn-sm px-4">
                                                    Save Changes
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

<!-- Modal Add Class -->
<div class="modal fade" id="addClassModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content text-start">
            <form method="POST" action="">
                <?= csrf_field() ?>
                <div class="modal-header bg-islamic text-white">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-plus-circle me-2 text-gold"></i> Add New Islamiyya Class
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Class / Stream Name <span class="text-danger">*</span></label>
                        <input type="text" name="class_name" class="form-control" required placeholder="e.g. Ibtida'iyyah 4 (Primary 4)">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Level Category <span class="text-danger">*</span></label>
                        <select name="level_category" class="form-select" required>
                            <option value="Rawdah">Rawdah (Early Childhood / Kindergarten)</option>
                            <option value="Tahfeez">Tahfeez (Quran Memorization Track)</option>
                            <option value="Ibtida'iyyah">Ibtida'iyyah (Primary Level)</option>
                            <option value="Mutawassitah">Mutawassitah (Junior Islamic Studies)</option>
                            <option value="Thanawiyyah">Thanawiyyah (Senior Islamic Studies)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Display Sequence Order</label>
                        <input type="number" name="display_order" class="form-control" value="<?= count($classes) + 1 ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Curriculum Focus / Description</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Books studied, Surahs covered, or special instructions..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_class" value="1" class="btn btn-islamic btn-sm px-4">
                        Save Class
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
