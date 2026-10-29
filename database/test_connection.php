<?php
/**
 * Database Connection & Environment Health Check
 * Flood Risk Prediction & Relief Camp Locator
 */

header('Content-Type: text/html; charset=utf-8');
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/core/Database.php';

$checks = [];

// 1. PHP Version
$checks['PHP Version (>= 8.0 required)'] = [
    'status' => version_compare(PHP_VERSION, '8.0.0', '>='),
    'detail' => 'Current: ' . PHP_VERSION
];

// 2. Required PHP Extensions
$extensions = ['pdo', 'pdo_pgsql', 'simplexml', 'dom', 'json', 'mbstring'];
foreach ($extensions as $ext) {
    $checks["PHP Extension '{$ext}'"] = [
        'status' => extension_loaded($ext),
        'detail' => extension_loaded($ext) ? 'Loaded' : 'Missing! Please enable in php.ini'
    ];
}

// 3. Database Connection
$dbConnected = false;
$dbError = null;
$tableStats = [];

try {
    $db = Database::getInstance();
    $dbConnected = $db->isConnected();
    if (!$dbConnected) {
        $dbError = $db->getLastError();
    } else {
        if ($db->getDriverName() === 'pgsql') {
            $tables = $db->fetchAll("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE' ORDER BY table_name");
            foreach ($tables as $t) {
                $tableName = $t['table_name'];
                $count = $db->fetchValue("SELECT COUNT(*) FROM {$tableName}");
                $tableStats[$tableName] = $count;
            }
        } else {
            $tables = $db->fetchAll("SHOW TABLES");
            foreach ($tables as $t) {
                $tableName = reset($t);
                $count = $db->fetchValue("SELECT COUNT(*) FROM `{$tableName}`");
                $tableStats[$tableName] = $count;
            }
        }
    }
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}

$checks['PostgreSQL Database Connection'] = [
    'status' => $dbConnected,
    'detail' => $dbConnected ? "Connected successfully to '" . DB_NAME . "' (" . $db->getDriverName() . ")" : ("Connection failed: " . ($dbError ?: 'Unknown error'))
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System & Database Diagnostics | <?= htmlspecialchars(APP_NAME) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-light py-5">
<div class="container" style="max-width: 800px;">
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-primary text-white py-3">
            <h4 class="mb-0"><i class="bi bi-shield-check me-2"></i><?= htmlspecialchars(APP_NAME) ?></h4>
            <small class="text-white-50">System Environment & Database Health Diagnostics</small>
        </div>
        <div class="card-body">
            <h5 class="card-title mb-3">Pre-Flight System Checks</h5>
            <ul class="list-group mb-4">
                <?php foreach ($checks as $title => $info): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <strong><?= htmlspecialchars($title) ?></strong><br>
                            <small class="text-muted"><?= htmlspecialchars($info['detail']) ?></small>
                        </div>
                        <?php if ($info['status']): ?>
                            <span class="badge bg-success rounded-pill px-3 py-2"><i class="bi bi-check-circle-fill me-1"></i> PASS</span>
                        <?php else: ?>
                            <span class="badge bg-danger rounded-pill px-3 py-2"><i class="bi bi-x-circle-fill me-1"></i> FAIL</span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if ($dbConnected): ?>
                <h5 class="card-title mb-3">Database Tables (<?= count($tableStats) ?> total)</h5>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Table Name</th>
                                <th class="text-center" style="width: 150px;">Record Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tableStats as $tbl => $cnt): ?>
                                <tr>
                                    <td><code><?= htmlspecialchars($tbl) ?></code></td>
                                    <td class="text-center">
                                        <span class="badge <?= $cnt > 0 ? 'bg-primary' : 'bg-secondary' ?>"><?= $cnt ?> rows</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="alert alert-success mt-3 mb-0">
                    <i class="bi bi-check-circle-fill me-2"></i> Database is fully initialized and operational!
                </div>
            <?php else: ?>
                <div class="alert alert-warning mt-3 mb-0">
                    <h6 class="alert-heading"><i class="bi bi-exclamation-triangle-fill me-2"></i>Database Setup Required</h6>
                    <p class="mb-2">To initialize the database in XAMPP / MySQL:</p>
                    <ol class="mb-2">
                        <li>Open phpMyAdmin at <a href="http://localhost/phpmyadmin/" target="_blank">http://localhost/phpmyadmin/</a>.</li>
                        <li>Create a database named <code>flood_relief_db</code>.</li>
                        <li>Import <code>database/schema.sql</code>.</li>
                        <li>Import <code>database/seed_data.sql</code>.</li>
                    </ol>
                    <p class="mb-0 text-muted">Or verify credentials in <code>config/database.php</code>.</p>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-footer bg-white text-muted text-center py-3">
            <a href="<?= BASE_URL ?>/views/citizen/index.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-arrow-left me-1"></i> Go to Public Dashboard</a>
        </div>
    </div>
</div>
</body>
</html>
