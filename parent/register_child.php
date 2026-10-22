<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
require_parent();

$pdo = get_db_connection();
$parent = get_current_parent_profile() ?? ['id' => 0, 'full_name' => 'Parent'];
$parent_id = (int)$parent['id'];

// Fetch available active classes for selection
$stmt_classes = $pdo->query("SELECT id, class_name, level_category FROM classes WHERE status = 'active' ORDER BY display_order ASC");
$classes = $stmt_classes->fetchAll();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security token expired. Please try again.';
    } else {
        $children_data = $_POST['children'] ?? [];

        if (empty($children_data) || !is_array($children_data)) {
            $errors[] = 'Please fill in the registration details for at least one child.';
        } else {
            $inserted_count = 0;
            $pdo->beginTransaction();
            try {
                $stmt_insert = $pdo->prepare("
                    INSERT INTO students (
                        parent_id, first_name, last_name, other_name, 
                        date_of_birth, gender, requested_class_id, 
                        previous_islamiyya_education, registration_status, enrolment_status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'active')
                ");

                foreach ($children_data as $index => $child) {
                    $first_name = trim($child['first_name'] ?? '');
                    $last_name = trim($child['last_name'] ?? '');
                    $other_name = trim($child['other_name'] ?? '');
                    $dob = trim($child['date_of_birth'] ?? '');
                    $gender = trim($child['gender'] ?? '');
                    $requested_class_id = !empty($child['requested_class_id']) ? (int)$child['requested_class_id'] : null;
                    $previous_edu = trim($child['previous_islamiyya_education'] ?? '');

                    if (empty($first_name) || empty($last_name) || empty($dob) || empty($gender) || empty($requested_class_id)) {
                        throw new Exception("Please ensure First Name, Last Name, Date of Birth, Gender, and Requested Class are completed for all child forms.");
                    }

                    $stmt_insert->execute([
                        $parent_id, $first_name, $last_name, $other_name,
                        $dob, $gender, $requested_class_id,
                        $previous_edu
                    ]);
                    $inserted_count++;
                }

                $pdo->commit();

                // Log audit
                log_audit('STUDENT_REGISTRATION_SUBMITTED', 'students', null, "Parent {$parent['full_name']} submitted registration for {$inserted_count} child(ren).");

                // In-app notification to Admin
                $admin_id = 1;
                $notif_title = "New Student Registration ({$inserted_count} Children)";
                $notif_msg = "Parent {$parent['full_name']} has submitted {$inserted_count} new child application(s) awaiting approval.";
                $notif_stmt = $pdo->prepare("INSERT INTO notifications (sender_id, title, message, target_type) VALUES (?, ?, ?, 'all')");
                $notif_stmt->execute([$_SESSION['user_id'], $notif_title, $notif_msg]);

                set_flash('success', "Alhamdulillah! {$inserted_count} child registration form(s) submitted successfully. The school administrator will review and assign student IDs.");
                header('Location: ' . BASE_URL . '/parent/my_children.php');
                exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = $e->getMessage();
            }
        }
    }
}

$page_title = "Register Child / Children";
$active_tab = "register_child";
require_once __DIR__ . '/../includes/parent_header.php';
?>

<!-- Class options template for JS cloning -->
<template id="class_options_template">
    <option value="">-- Choose Islamiyya Level --</option>
    <?php foreach ($classes as $c): ?>
        <option value="<?= $c['id'] ?>"><?= e($c['class_name']) ?> (<?= e($c['level_category']) ?>)</option>
    <?php endforeach; ?>
</template>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
            <div>
                <h3 class="fw-bold text-islamic mb-1">
                    <i class="bi bi-person-plus-fill text-gold me-2"></i> Register Your Child / Children
                </h3>
                <p class="text-muted small mb-0">You can register one or multiple children in a single submission.</p>
            </div>
            <a href="<?= BASE_URL ?>/parent/my_children.php" class="btn btn-outline-islamic btn-sm">
                <i class="bi bi-people me-1"></i> View Registered Children
            </a>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger small shadow-sm">
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Number of Children Selector Card -->
        <div class="card card-modern p-4 mb-4 border-start border-4 border-warning">
            <div class="row align-items-center">
                <div class="col-md-7">
                    <h5 class="fw-bold text-islamic mb-1">How many children are you enrolling today?</h5>
                    <p class="text-muted small mb-0">Select the number of children to automatically generate separate registration forms for each child.</p>
                </div>
                <div class="col-md-5 mt-3 mt-md-0">
                    <div class="input-group">
                        <span class="input-group-text bg-light fw-bold text-islamic"><i class="bi bi-people-fill me-1"></i> Number of Children:</span>
                        <select id="num_children_select" class="form-select form-select-lg fw-bold text-islamic">
                            <option value="1" selected>1 Child</option>
                            <option value="2">2 Children</option>
                            <option value="3">3 Children</option>
                            <option value="4">4 Children</option>
                            <option value="5">5 Children</option>
                            <option value="6">6 Children</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dynamic Child Forms Container -->
        <form method="POST" action="" id="multi_child_form">
            <?= csrf_field() ?>

            <!-- Container rendered dynamically by JavaScript -->
            <div id="children_forms_container">
                <!-- Rendered by main.js -->
            </div>

            <!-- Parent Information Confirmation Note -->
            <div class="card card-modern p-3 mb-4 bg-light border">
                <div class="d-flex align-items-center">
                    <i class="bi bi-info-circle-fill text-islamic fs-4 me-3"></i>
                    <div class="small text-muted">
                        All registered children will be automatically linked to your parent account: 
                        <strong class="text-dark"><?= e($parent['full_name']) ?> (<?= e($parent['phone']) ?>)</strong>.
                        You can update your address or emergency contact anytime in your Profile.
                    </div>
                </div>
            </div>

            <div class="text-end mb-5">
                <button type="submit" class="btn btn-islamic btn-lg px-5 shadow">
                    <i class="bi bi-send-check me-2"></i> Submit Registration Applications
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/parent_footer.php'; ?>
