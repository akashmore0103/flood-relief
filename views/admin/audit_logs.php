<?php
/**
 * Administrator System Security & Operational Audit Trail
 * Satisfies PRD Section 11 (Auditability)
 */

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once dirname(__DIR__, 2) . '/core/Database.php';
require_once dirname(__DIR__, 2) . '/core/Auth.php';
require_once dirname(__DIR__, 2) . '/core/Helper.php';

Auth::requireRole(['Administrator']);

$db = Database::getInstance();

$filterAction = $_GET['action'] ?? 'All';

$sql = "SELECT a.*, u.full_name, u.email 
        FROM `audit_logs` a
        LEFT JOIN `users` u ON a.user_id = u.id
        WHERE 1=1";
$params = [];

if ($filterAction !== 'All' && !empty($filterAction)) {
    $sql .= " AND a.action_type = :action";
    $params[':action'] = $filterAction;
}

$sql .= " ORDER BY a.created_at DESC LIMIT 100";
$logs = $db->fetchAll($sql, $params);

// Fetch distinct actions for filter dropdown
$distinctActions = $db->fetchAll("SELECT DISTINCT `action_type` FROM `audit_logs` ORDER BY `action_type` ASC");

$pageTitle = "System Audit Trail";
require_once dirname(__DIR__) . '/layouts/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="bi bi-clock-history text-primary me-2"></i>Security &amp; Operational Audit Trail
            </h2>
            <p class="text-muted small mb-0">Complete chronological audit record of threshold changes, alert broadcasts, data imports, and logins.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <label for="actionFilter" class="small fw-semibold text-muted text-nowrap">Filter Action:</label>
            <select id="actionFilter" class="form-select form-select-sm" onchange="location.href = '?action=' + this.value">
                <option value="All">-- All Action Types --</option>
                <?php foreach ($distinctActions as $act): ?>
                    <option value="<?= Helper::e($act['action_type']) ?>" <?= $act['action_type'] === $filterAction ? 'selected' : '' ?>>
                        <?= Helper::e($act['action_type']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Logs Table Card -->
    <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark small">
                    <tr>
                        <th style="width: 170px;">Timestamp</th>
                        <th>Action Type</th>
                        <th>Actor / User</th>
                        <th>Table &amp; Record</th>
                        <th>Event Details</th>
                        <th>IP Address</th>
                    </tr>
                </thead>
                <tbody class="small font-monospace">
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted">No audit events found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="text-muted"><?= Helper::formatDateTime($log['created_at']) ?></td>
                                <td>
                                    <span class="badge bg-secondary"><?= Helper::e($log['action_type']) ?></span>
                                </td>
                                <td>
                                    <?= Helper::e($log['full_name'] ? "{$log['full_name']} ({$log['email']})" : 'System / Guest') ?>
                                </td>
                                <td>
                                    <?= Helper::e($log['table_affected'] ?: '-') ?> 
                                    <?= !empty($log['record_id']) ? "(#{$log['record_id']})" : '' ?>
                                </td>
                                <td class="font-sans-serif" style="font-family: system-ui;">
                                    <?= Helper::e($log['details']) ?>
                                </td>
                                <td class="text-muted"><?= Helper::e($log['ip_address'] ?: '127.0.0.1') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
