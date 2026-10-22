<?php
/**
 * Web-based Database Installer & Setup Helper
 * Islamiyya School Management System (ISMS)
 */
require_once __DIR__ . '/config/database.php';

$installed = false;
$error = '';
$output_messages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_install'])) {
    try {
        $pdo = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";charset=utf8mb4", DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        $output_messages[] = "Connected to MySQL server on " . DB_HOST . ":" . DB_PORT;

        $schema_file = __DIR__ . '/database/schema.sql';
        if (!file_exists($schema_file)) {
            throw new Exception("Schema file not found at " . $schema_file);
        }

        $sql = file_get_contents($schema_file);
        $pdo->exec($sql);
        $output_messages[] = "Database `" . DB_NAME . "` created and all 17 tables seeded successfully!";

        // Verify tables
        $pdo->exec("USE `" . DB_NAME . "`");
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $output_messages[] = "Total tables installed: " . count($tables);

        $installed = true;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ISMS Database Installer & Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background-color: #f5f8f6; }
        .installer-card { max-width: 650px; margin: 4rem auto; border-radius: 12px; border: none; box-shadow: 0 4px 20px rgba(0,0,0,0.06); }
        .bg-islamic { background-color: #0d5c46 !important; color: #fff; }
        .btn-islamic { background-color: #0d5c46; border-color: #0d5c46; color: #fff; }
        .btn-islamic:hover { background-color: #083e2f; color: #fff; }
        .text-gold { color: #c59b27 !important; }
    </style>
</head>
<body>

<div class="container">
    <div class="card installer-card">
        <div class="card-header bg-islamic p-4 text-center">
            <i class="bi bi-mortarboard-fill fs-1 text-gold"></i>
            <h3 class="fw-bold mb-1">Islamiyya School Management System</h3>
            <p class="small text-white-50 mb-0">ISMS Nigeria &bull; Database Setup & Verification</p>
        </div>
        <div class="card-body p-4 p-md-5">
            <?php if ($installed): ?>
                <div class="alert alert-success d-flex align-items-center mb-4">
                    <i class="bi bi-check-circle-fill fs-3 me-3 text-success"></i>
                    <div>
                        <h5 class="fw-bold mb-1">Installation Successful!</h5>
                        <div>The database has been configured and populated with sample Islamic school data.</div>
                    </div>
                </div>

                <div class="bg-light p-3 rounded-3 mb-4 font-monospace small">
                    <?php foreach ($output_messages as $msg): ?>
                        <div class="text-success">&bull; <?= htmlspecialchars($msg) ?></div>
                    <?php endforeach; ?>
                </div>

                <div class="card border-0 bg-white shadow-sm p-3 mb-4">
                    <h6 class="fw-bold text-dark mb-2"><i class="bi bi-key-fill text-gold me-2"></i> Default Login Credentials:</h6>
                    <div class="row g-2 small">
                        <div class="col-6">
                            <div class="p-2 border rounded">
                                <strong class="text-success d-block">Admin Account:</strong>
                                Username: <code>admin</code><br>
                                Password: <code>Admin@123</code>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 border rounded">
                                <strong class="text-primary d-block">Sample Parent:</strong>
                                Username: <code>parent_ibrahim</code><br>
                                Password: <code>Parent@123</code>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <a href="index.php" class="btn btn-outline-secondary flex-fill">Public Website</a>
                    <a href="auth/login.php" class="btn btn-islamic flex-fill">Go to Portal Login</a>
                </div>

            <?php else: ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger small mb-4">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> Error: <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <h5 class="fw-bold text-dark mb-2">Automated Database Setup</h5>
                <p class="text-muted small mb-4">
                    Click the button below to initialize or reset the <code>islamiyya_db</code> database in MySQL. This will build all 17 normalized relational tables and seed initial classes, fee structures, settings, and accounts.
                </p>

                <div class="p-3 bg-light rounded-3 mb-4 small">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Host & Port:</span>
                        <strong><?= DB_HOST ?>:<?= DB_PORT ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted">Database:</span>
                        <strong><?= DB_NAME ?></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted">MySQL User:</span>
                        <strong><?= DB_USER ?></strong>
                    </div>
                </div>

                <form method="POST" action="">
                    <button type="submit" name="run_install" value="1" class="btn btn-islamic w-100 py-3 fw-bold shadow-sm">
                        <i class="bi bi-database-fill-gear me-2"></i> Initialize / Reset Database Now
                    </button>
                </form>

                <div class="text-center mt-4">
                    <a href="index.php" class="text-decoration-none small text-muted">
                        <i class="bi bi-arrow-left me-1"></i> Return to Homepage
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>
