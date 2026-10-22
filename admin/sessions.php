<?php
$page_title = "Academic Sessions & Terms";
$active_tab = "sessions";
require_once __DIR__ . '/../includes/admin_header.php';

$pdo = get_db_connection();

// Set Current Session & Term POST
if (isset($_POST['set_current_term']) && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $session_id = (int)$_POST['session_id'];
    $term_id = (int)$_POST['term_id'];

    try {
        $pdo->beginTransaction();

        // Deactivate all sessions, activate selected
        $pdo->exec("UPDATE academic_sessions SET is_current = 0");
        $stmt_s = $pdo->prepare("UPDATE academic_sessions SET is_current = 1 WHERE id = ?");
        $stmt_s->execute([$session_id]);

        // Deactivate all terms, activate selected
        $pdo->exec("UPDATE academic_terms SET is_current = 0");
        $stmt_t = $pdo->prepare("UPDATE academic_terms SET is_current = 1 WHERE id = ? AND session_id = ?");
        $stmt_t->execute([$term_id, $session_id]);

        $pdo->commit();
        log_audit('SESSION_TERM_ACTIVATED', 'academic_sessions', $session_id, "Set active session {$session_id}, term {$term_id}");
        set_flash('success', "Current academic session and term successfully updated.");
        header('Location: ' . BASE_URL . '/admin/sessions.php');
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        set_flash('danger', "Error activating term: " . $e->getMessage());
    }
}

// Add New Academic Session POST
if (isset($_POST['add_session']) && verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $session_name = trim($_POST['session_name']);
    if (!empty($session_name)) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO academic_sessions (session_name, is_current) VALUES (?, 0)");
            $stmt->execute([$session_name]);
            $new_sess_id = (int)$pdo->lastInsertId();

            // Auto-create standard 3 terms for this session
            $stmt_term = $pdo->prepare("INSERT INTO academic_terms (session_id, term_name, is_current) VALUES (?, ?, 0)");
            $stmt_term->execute([$new_sess_id, 'First Term']);
            $stmt_term->execute([$new_sess_id, 'Second Term']);
            $stmt_term->execute([$new_sess_id, 'Third Term']);

            $pdo->commit();
            log_audit('SESSION_CREATED', 'academic_sessions', $new_sess_id, "Created academic session {$session_name}");
            set_flash('success', "Academic session '{$session_name}' created with First, Second, and Third terms.");
            header('Location: ' . BASE_URL . '/admin/sessions.php');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            set_flash('danger', "Failed to add session: " . $e->getMessage());
        }
    }
}

// Fetch all sessions with their terms
$sessions = $pdo->query("SELECT * FROM academic_sessions ORDER BY id DESC")->fetchAll();
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold text-islamic mb-1">
            <i class="bi bi-calendar3 text-gold me-2"></i> Academic Sessions & Terms
        </h3>
        <p class="text-muted small mb-0">Manage school calendars, terms, and configure the active school term for invoicing.</p>
    </div>
    <button type="button" class="btn btn-gold btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#addSessionModal">
        <i class="bi bi-plus-circle me-1"></i> Add Academic Session
    </button>
</div>

<div class="row g-4">
    <!-- Active Session Selector Card -->
    <div class="col-lg-5">
        <div class="card card-modern p-4 border-start border-4 border-success">
            <h5 class="fw-bold text-islamic mb-3">
                <i class="bi bi-clock-history text-gold me-2"></i> Active Academic Period
            </h5>
            <p class="small text-muted mb-3">
                The active period controls which term's fee structure is loaded for invoices and displayed on parent dashboards.
            </p>

            <form method="POST" action="">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Academic Session <span class="text-danger">*</span></label>
                    <select name="session_id" id="session_select" class="form-select" required>
                        <?php foreach ($sessions as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= $s['is_current'] ? 'selected' : '' ?>>
                                <?= e($s['session_name']) ?> <?= $s['is_current'] ? '(Currently Active)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold">School Term <span class="text-danger">*</span></label>
                    <select name="term_id" class="form-select" required>
                        <?php 
                        $cur_sess_id = $cur_term['session_id'];
                        $terms = $pdo->prepare("SELECT * FROM academic_terms WHERE session_id = ? ORDER BY id ASC");
                        $terms->execute([$cur_sess_id]);
                        foreach ($terms->fetchAll() as $t): ?>
                            <option value="<?= $t['id'] ?>" <?= $t['is_current'] ? 'selected' : '' ?>>
                                <?= e($t['term_name']) ?> <?= $t['is_current'] ? '(Active)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <button type="submit" name="set_current_term" value="1" class="btn btn-islamic w-100 py-2">
                    <i class="bi bi-check2-circle me-1"></i> Set as Active Session & Term
                </button>
            </form>
        </div>
    </div>

    <!-- All Sessions List -->
    <div class="col-lg-7">
        <div class="card card-modern">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <h5 class="mb-0 text-islamic fw-bold">All Academic Sessions</h5>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">
                    <?php foreach ($sessions as $s): ?>
                        <?php 
                        $stmt_t = $pdo->prepare("SELECT * FROM academic_terms WHERE session_id = ? ORDER BY id ASC");
                        $stmt_t->execute([$s['id']]);
                        $s_terms = $stmt_t->fetchAll();
                        ?>
                        <div class="list-group-item p-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h5 class="fw-bold text-islamic mb-0">
                                    Academic Session: <?= e($s['session_name']) ?>
                                </h5>
                                <?php if ($s['is_current']): ?>
                                    <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i> Current Active Session</span>
                                <?php endif; ?>
                            </div>
                            <div class="row g-2 mt-2">
                                <?php foreach ($s_terms as $term_item): ?>
                                    <div class="col-md-4">
                                        <div class="p-2 border rounded <?= $term_item['is_current'] ? 'bg-islamic-soft border-success' : 'bg-light' ?> small">
                                            <div class="fw-bold <?= $term_item['is_current'] ? 'text-islamic' : 'text-dark' ?>">
                                                <?= e($term_item['term_name']) ?>
                                                <?php if ($term_item['is_current']): ?>
                                                    <span class="badge bg-gold ms-1">Active</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-muted" style="font-size: 0.72rem;">
                                                <?= $term_item['start_date'] ? format_date($term_item['start_date']) . ' - ' . format_date($term_item['end_date']) : 'Dates to be announced' ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Add Session -->
<div class="modal fade" id="addSessionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content text-start">
            <form method="POST" action="">
                <?= csrf_field() ?>
                <div class="modal-header bg-islamic text-white">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-calendar-plus me-2 text-gold"></i> Create Academic Session
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Academic Session Name <span class="text-danger">*</span></label>
                        <input type="text" name="session_name" class="form-control" required placeholder="e.g. 2026/2027">
                        <small class="text-muted">Standard Nigerian format: YYYY/YYYY</small>
                    </div>
                    <div class="alert alert-info py-2 small mb-0">
                        <i class="bi bi-info-circle me-1"></i> First Term, Second Term, and Third Term will be automatically created under this session.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_session" value="1" class="btn btn-islamic btn-sm px-4">
                        Create Session
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/admin_footer.php'; ?>
