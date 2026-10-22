<?php
$page_title = "Manage School Fees";
$active_tab = "fees";
require_once __DIR__ . '/../includes/admin_header.php';

$pdo = get_db_connection();
$cur_term = get_current_session_term();

// Fetch sessions, terms, classes, categories
$sessions = $pdo->query("SELECT * FROM academic_sessions ORDER BY id DESC")->fetchAll();
$terms = $pdo->query("SELECT * FROM academic_terms ORDER BY id ASC")->fetchAll();
$classes = $pdo->query("SELECT * FROM classes WHERE status = 'active' ORDER BY display_order ASC")->fetchAll();
$categories = $pdo->query("SELECT * FROM fee_categories ORDER BY id ASC")->fetchAll();

// Add Fee Structure POST
if (isset($_POST['add_fee_structure']) && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $session_id = (int)$_POST['session_id'];
    $term_id = (int)$_POST['term_id'];
    $class_id = (int)$_POST['class_id'];
    $fee_category_id = (int)$_POST['fee_category_id'];
    $amount = (float)$_POST['amount'];
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;

    if ($amount >= 0 && $class_id && $fee_category_id) {
        // Check if exists
        $chk = $pdo->prepare("SELECT id FROM fee_structures WHERE session_id = ? AND term_id = ? AND class_id = ? AND fee_category_id = ?");
        $chk->execute([$session_id, $term_id, $class_id, $fee_category_id]);
        if ($chk->rowCount() > 0) {
            $stmt = $pdo->prepare("UPDATE fee_structures SET amount = ?, due_date = ? WHERE session_id = ? AND term_id = ? AND class_id = ? AND fee_category_id = ?");
            $stmt->execute([$amount, $due_date, $session_id, $term_id, $class_id, $fee_category_id]);
            set_flash('success', "Fee structure updated successfully.");
        } else {
            $stmt = $pdo->prepare("INSERT INTO fee_structures (session_id, term_id, class_id, fee_category_id, amount, due_date) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$session_id, $term_id, $class_id, $fee_category_id, $amount, $due_date]);
            set_flash('success', "Fee structure configured successfully.");
        }
        log_audit('FEE_STRUCTURE_UPDATED', 'fee_structures', null, "Set fee structure for class {$class_id}, category {$fee_category_id}: ₦{$amount}");
        header('Location: ' . BASE_URL . '/admin/fees.php');
        exit;
    }
}

// Add New Fee Category POST
if (isset($_POST['add_fee_category']) && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $cat_name = trim($_POST['category_name']);
    $cat_desc = trim($_POST['description'] ?? '');
    if (!empty($cat_name)) {
        $stmt = $pdo->prepare("INSERT INTO fee_categories (name, description) VALUES (?, ?)");
        $stmt->execute([$cat_name, $cat_desc]);
        log_audit('FEE_CATEGORY_ADDED', 'fee_categories', (int)$pdo->lastInsertId(), "Added fee category {$cat_name}");
        set_flash('success', "Fee category '{$cat_name}' created successfully.");
        header('Location: ' . BASE_URL . '/admin/fees.php');
        exit;
    }
}

// Delete Fee Structure Item POST
if (isset($_POST['delete_fee_item']) && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $item_id = (int)$_POST['fee_structure_id'];
    $stmt = $pdo->prepare("DELETE FROM fee_structures WHERE id = ?");
    $stmt->execute([$item_id]);
    set_flash('info', "Fee item removed.");
    header('Location: ' . BASE_URL . '/admin/fees.php');
    exit;
}

// Selected Filter
$selected_session_id = (int)($_GET['session_id'] ?? $cur_term['session_id']);
$selected_term_id = (int)($_GET['term_id'] ?? $cur_term['term_id']);

// Fetch fee structures
$stmt_fees = $pdo->prepare("
    SELECT fs.*, c.class_name, c.level_category, fc.name as category_name, s.session_name, t.term_name
    FROM fee_structures fs
    JOIN classes c ON fs.class_id = c.id
    JOIN fee_categories fc ON fs.fee_category_id = fc.id
    JOIN academic_sessions s ON fs.session_id = s.id
    JOIN academic_terms t ON fs.term_id = t.id
    WHERE fs.session_id = ? AND fs.term_id = ?
    ORDER BY c.display_order ASC, fc.id ASC
");
$stmt_fees->execute([$selected_session_id, $selected_term_id]);
$fee_structures = $stmt_fees->fetchAll();
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold text-islamic mb-1">
            <i class="bi bi-tags-fill text-gold me-2"></i> School Fee Structures
        </h3>
        <p class="text-muted small mb-0">Configure tuition, books, exams, and registration fees in Nigerian Naira (₦) by class and term.</p>
    </div>
    <div class="mt-3 mt-md-0 d-flex gap-2">
        <button type="button" class="btn btn-outline-islamic btn-sm" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
            <i class="bi bi-folder-plus me-1"></i> Add Fee Category
        </button>
        <button type="button" class="btn btn-gold btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#addFeeModal">
            <i class="bi bi-plus-circle me-1"></i> Configure Class Fee
        </button>
    </div>
</div>

<!-- Session & Term Filter Form -->
<div class="card card-modern p-3 mb-4">
    <form method="GET" action="" class="row g-2 align-items-center">
        <div class="col-md-5">
            <div class="input-group">
                <span class="input-group-text bg-light fw-bold text-islamic">Session:</span>
                <select name="session_id" class="form-select">
                    <?php foreach ($sessions as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $selected_session_id == $s['id'] ? 'selected' : '' ?>>
                            <?= e($s['session_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="col-md-5">
            <div class="input-group">
                <span class="input-group-text bg-light fw-bold text-islamic">Term:</span>
                <select name="term_id" class="form-select">
                    <?php foreach ($terms as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= $selected_term_id == $t['id'] ? 'selected' : '' ?>>
                            <?= e($t['term_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-islamic w-100">Load Fees</button>
        </div>
    </form>
</div>

<!-- Fee Structure Table -->
<div class="card card-modern">
    <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
        <h5 class="mb-0 text-islamic fw-bold">
            Configured Fees (<?= count($fee_structures) ?>)
        </h5>
        <span class="badge bg-gold text-dark"><?= count($classes) ?> Classes Available</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Islamiyya Class</th>
                        <th>Category</th>
                        <th>Fee Amount (₦)</th>
                        <th>Academic Period</th>
                        <th>Payment Deadline</th>
                        <th class="text-end pe-4">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($fee_structures)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-tags fs-1 d-block mb-2 text-muted"></i>
                                No fee structures configured for this session and term. Click "Configure Class Fee" above to set fees.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($fee_structures as $fs): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-islamic"><?= e($fs['class_name']) ?></div>
                                    <small class="text-muted"><?= e($fs['level_category']) ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-islamic-soft text-islamic fw-semibold"><?= e($fs['category_name']) ?></span>
                                </td>
                                <td class="fw-bold text-dark fs-6"><?= format_currency($fs['amount']) ?></td>
                                <td><?= e($fs['session_name']) ?> &bull; <?= e($fs['term_name']) ?></td>
                                <td><?= $fs['due_date'] ? format_date($fs['due_date']) : '<span class="text-muted small">None</span>' ?></td>
                                <td class="text-end pe-4">
                                    <form method="POST" action="" onsubmit="return confirmAction('Delete this fee item?')" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="fee_structure_id" value="<?= $fs['id'] ?>">
                                        <button type="submit" name="delete_fee_item" value="1" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Configure Fee Structure -->
<div class="modal fade" id="addFeeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content text-start">
            <form method="POST" action="">
                <?= csrf_field() ?>
                <input type="hidden" name="session_id" value="<?= $selected_session_id ?>">
                <input type="hidden" name="term_id" value="<?= $selected_term_id ?>">

                <div class="modal-header bg-islamic text-white">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-tag-fill me-2 text-gold"></i> Configure Fee for Class
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Target Islamiyya Class <span class="text-danger">*</span></label>
                        <select name="class_id" class="form-select" required>
                            <option value="">-- Choose Class --</option>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= e($c['class_name']) ?> (<?= e($c['level_category']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Fee Category <span class="text-danger">*</span></label>
                        <select name="fee_category_id" class="form-select" required>
                            <option value="">-- Choose Category --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= e($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Amount in Naira (₦) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-light fw-bold">₦</span>
                            <input type="number" step="0.01" min="0" name="amount" class="form-control fw-bold" placeholder="e.g. 25000" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Payment Deadline (Optional)</label>
                        <input type="date" name="due_date" class="form-control" value="<?= date('Y-m-d', strtotime('+30 days')) ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_fee_structure" value="1" class="btn btn-islamic btn-sm px-4">
                        Save Fee Structure
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Add Category -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content text-start">
            <form method="POST" action="">
                <?= csrf_field() ?>
                <div class="modal-header bg-gold text-white">
                    <h5 class="modal-title fw-bold">Add New Fee Category</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Category Name <span class="text-danger">*</span></label>
                        <input type="text" name="category_name" class="form-control" required placeholder="e.g. School Bus Transport / Excursion Fee">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Purpose of this fee category..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_fee_category" value="1" class="btn btn-gold btn-sm px-4">
                        Create Category
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
