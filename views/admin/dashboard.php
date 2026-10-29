<?php
/**
 * Administrator Overview & Command Center
 * Satisfies PRD Section 6 (Admin Panel)
 */

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once dirname(__DIR__, 2) . '/core/Database.php';
require_once dirname(__DIR__, 2) . '/core/Auth.php';
require_once dirname(__DIR__, 2) . '/core/Helper.php';
require_once dirname(__DIR__, 2) . '/services/AlertService.php';

Auth::requireRole(['Administrator']);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Suppress bulky "Welcome back" flash banner to keep admin dashboard clean & modern
if (isset($_SESSION['flash_messages']) && is_array($_SESSION['flash_messages'])) {
    $_SESSION['flash_messages'] = array_values(array_filter($_SESSION['flash_messages'], function($f) {
        return !str_contains(strtolower($f['message'] ?? ''), 'welcome back');
    }));
}

$db = Database::getInstance();

// Active Emergency Alerts for live management & revoking
$activeAlertsList = AlertService::getActiveAlerts();

// Metric Aggregations
$metrics = [
    'zones_count' => (int)$db->fetchValue("SELECT COUNT(*) FROM `zones`"),
    'critical_zones' => (int)$db->fetchValue("
        SELECT COUNT(DISTINCT z.id) 
        FROM `zones` z
        JOIN `risk_levels` rl ON rl.id = (
            SELECT id FROM `risk_levels` WHERE zone_id = z.id ORDER BY observed_at DESC, id DESC LIMIT 1
        )
        WHERE z.status = 'active' AND rl.category = 'Critical'
    "),
    'camps_count' => (int)$db->fetchValue("SELECT COUNT(*) FROM `camps` WHERE `active` = TRUE"),
    'total_beds' => (int)$db->fetchValue("SELECT SUM(`total_capacity`) FROM `camps` WHERE `active` = TRUE"),
    'occupied_beds' => (int)$db->fetchValue("SELECT SUM(`current_occupancy`) FROM `camps` WHERE `active` = TRUE"),
    'total_requests' => (int)$db->fetchValue("SELECT COUNT(*) FROM `help_requests`"),
    'new_requests' => (int)$db->fetchValue("SELECT COUNT(*) FROM `help_requests` WHERE `status` = 'New'"),
    'active_alerts' => count($activeAlertsList)
];

$bedOccupancyRate = $metrics['total_beds'] > 0 ? round(($metrics['occupied_beds'] / $metrics['total_beds']) * 100, 1) : 0;

// Recent Administrative Audit Logs
$recentLogs = $db->fetchAll("
    SELECT a.*, u.full_name, u.email 
    FROM `audit_logs` a
    LEFT JOIN `users` u ON a.user_id = u.id
    ORDER BY a.created_at DESC
    LIMIT 8
");

// Monitored Zones Overview
$zones = $db->fetchAll("
    SELECT z.*, rl.score, rl.category, rl.observed_at 
    FROM `zones` z
    LEFT JOIN `risk_levels` rl ON rl.id = (
        SELECT id FROM `risk_levels` WHERE zone_id = z.id ORDER BY observed_at DESC, id DESC LIMIT 1
    )
    ORDER BY rl.score DESC, z.name ASC
    LIMIT 5
");

// Retrieve all dams and basins for targeted geofenced broadcast
$allDams = $db->fetchAll("SELECT id, name, river_id, district FROM `dams` ORDER BY name ASC");
$allBasins = $db->fetchAll("SELECT id, name, river_id FROM `river_basins` ORDER BY name ASC");
if (empty($allBasins)) {
    $allBasins = $db->fetchAll("SELECT id, name, id AS river_id FROM `rivers` ORDER BY name ASC");
}

$pageTitle = "Administrator Command Center";
require_once dirname(__DIR__) . '/layouts/header.php';
?>

<div class="container py-4">
    <!-- Modern Command Center Layout Header (replaces bulky green alert banner) -->
    <div class="card glass-panel border-0 shadow-sm p-4 mb-4 rounded-4 bg-white">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
            <div>
                <!-- Minimal Status Indicators & Operational Role Chips -->
                <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1 font-monospace fw-semibold" style="font-size: 0.75rem;">
                        <i class="bi bi-broadcast-pin me-1"></i> TELEMETRY PIPELINE: OPERATIONAL
                    </span>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1 font-monospace fw-semibold" style="font-size: 0.75rem;">
                        <i class="bi bi-shield-lock-fill me-1"></i> ROLE: DISASTER OPS ADMIN
                    </span>
                    <span class="badge bg-dark-subtle text-dark border border-secondary-subtle rounded-pill px-3 py-1 font-monospace" style="font-size: 0.75rem;">
                        <i class="bi bi-clock-history me-1"></i> FEED REFRESH: REAL-TIME
                    </span>
                </div>
                <h2 class="fw-bold text-dark mb-1" style="font-size: 1.65rem;">
                    <i class="bi bi-speedometer2 text-warning me-2"></i>Administrator Command Center
                </h2>
                <p class="text-muted small mb-0">
                    <i class="bi bi-shield-check text-success me-1"></i> Operating as <strong><?= Helper::e(Auth::user()['full_name']) ?></strong> &bull; Hydrological risk calculation, automated warning dispatch, shelter capacity oversight, and staff audit trail.
                </p>
            </div>
            <div class="d-flex align-items-center flex-wrap gap-2">
                <a href="<?= BASE_URL ?>/views/admin/simulate_flood.php" class="btn btn-outline-danger btn-sm fw-semibold shadow-sm">
                    <i class="bi bi-lightning-charge-fill me-1"></i> Simulation Studio
                </a>
                <a href="<?= BASE_URL ?>/views/admin/import_data.php" class="btn btn-outline-info btn-sm text-dark fw-semibold shadow-sm">
                    <i class="bi bi-file-earmark-code me-1"></i> Ingest Feeds
                </a>
                <button type="button" class="btn btn-danger btn-sm fw-semibold shadow-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#targetedAlertModal">
                    <i class="bi bi-broadcast-pin me-1"></i> Target Dam Red Alert
                </button>
            </div>
        </div>
    </div>

    <!-- Executive Metrics Grid -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="card stat-card p-3 border-0 bg-white">
                <div class="d-flex align-items-center">
                    <div class="p-3 bg-danger bg-opacity-10 text-danger rounded-3 me-3 <?= $metrics['critical_zones'] > 0 ? 'pulse-emergency' : '' ?>">
                        <i class="bi bi-exclamation-triangle-fill fs-3"></i>
                    </div>
                    <div>
                        <div class="small text-muted fw-semibold">Critical Threat Zones</div>
                        <h3 class="fw-bold mb-0 text-danger"><?= $metrics['critical_zones'] ?> <small class="fs-6 text-muted">/ <?= $metrics['zones_count'] ?></small></h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card stat-card p-3 border-0 bg-white">
                <div class="d-flex align-items-center">
                    <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-3 me-3">
                        <i class="bi bi-bell-fill fs-3"></i>
                    </div>
                    <div>
                        <div class="small text-muted fw-semibold">Emergency Requests</div>
                        <h3 class="fw-bold mb-0 text-primary"><?= $metrics['new_requests'] ?> <small class="fs-6 text-muted">new / <?= $metrics['total_requests'] ?></small></h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card stat-card p-3 border-0 bg-white">
                <div class="d-flex align-items-center">
                    <div class="p-3 bg-success bg-opacity-10 text-success rounded-3 me-3">
                        <i class="bi bi-hospital-fill fs-3"></i>
                    </div>
                    <div>
                        <div class="small text-muted fw-semibold">Bed Occupancy Rate</div>
                        <h3 class="fw-bold mb-0 text-dark"><?= $bedOccupancyRate ?>% <small class="fs-6 text-muted">(<?= $metrics['occupied_beds'] ?>/<?= $metrics['total_beds'] ?>)</small></h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-lg-3">
            <div class="card stat-card p-3 border-0 bg-white">
                <div class="d-flex align-items-center">
                    <div class="p-3 bg-warning bg-opacity-10 text-warning rounded-3 me-3">
                        <i class="bi bi-broadcast fs-3"></i>
                    </div>
                    <div>
                        <div class="small text-muted fw-semibold">Active Public Alerts</div>
                        <h3 class="fw-bold mb-0 text-dark" id="metricActiveAlerts"><?= $metrics['active_alerts'] ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Emergency Bulletins & Alert Management -->
    <div class="card shadow-sm border-0 rounded-4 mb-4 bg-white overflow-hidden" id="activeAlertsSection">
        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <span class="p-2 bg-danger bg-opacity-10 text-danger rounded-3">
                    <i class="bi bi-broadcast fs-5"></i>
                </span>
                <div>
                    <h5 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                        Active Emergency Bulletins &amp; Public Alerts
                        <span class="badge bg-danger rounded-pill fs-7 font-monospace" id="activeAlertsBadge">
                            <?= count($activeAlertsList) ?> ACTIVE
                        </span>
                    </h5>
                    <small class="text-muted">Live emergency directives currently broadcasted to citizen dashboards and email alert feeds.</small>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <a href="<?= BASE_URL ?>/views/admin/alerts.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                    <i class="bi bi-clock-history me-1"></i> Broadcast History
                </a>
                <button type="button" class="btn btn-danger btn-sm rounded-pill px-3 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#targetedAlertModal">
                    <i class="bi bi-broadcast-pin me-1"></i> New Geofenced Alert
                </button>
            </div>
        </div>
        <div class="card-body p-4 pt-1">
            <div id="activeAlertsGrid" class="row g-3 <?= empty($activeAlertsList) ? 'd-none' : '' ?>">
                <?php foreach ($activeAlertsList as $a): 
                    $isCritical = in_array($a['severity'], ['Critical Danger', 'Evacuation']);
                    $borderClass = $isCritical ? 'border-danger' : 'border-warning';
                    $bgBadge = match($a['severity']) {
                        'Critical Danger' => 'danger',
                        'Evacuation' => 'danger',
                        'Warning' => 'warning text-dark',
                        default => 'info text-dark'
                    };
                ?>
                    <div class="col-md-6 col-xl-4 alert-card-item" id="alert-card-<?= $a['id'] ?>" data-alert-id="<?= $a['id'] ?>">
                        <div class="card h-100 border-2 <?= $borderClass ?> rounded-4 shadow-sm p-3 position-relative bg-white d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge bg-<?= $bgBadge ?> px-2 py-1 small fw-semibold">
                                    <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= Helper::e($a['severity']) ?>
                                </span>
                                <span class="badge bg-secondary-subtle text-secondary border small font-monospace">
                                    #<?= $a['id'] ?> &bull; <?= Helper::e($a['channel']) ?>
                                </span>
                            </div>

                            <h6 class="fw-bold text-dark mb-1 text-truncate" title="<?= Helper::e($a['title']) ?>">
                                <?= Helper::e($a['title']) ?>
                            </h6>

                            <p class="text-muted small mb-3 flex-grow-1" style="display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.45;">
                                <?= Helper::e($a['message']) ?>
                            </p>

                            <div class="p-2 bg-light rounded-3 mb-3 small border">
                                <div class="d-flex justify-content-between text-muted" style="font-size: 0.78rem;">
                                    <span>Target: <strong class="text-dark"><?= !empty($a['zone_name']) ? Helper::e($a['zone_name']) : 'All Citizens / Statewide' ?></strong></span>
                                    <span>Issued: <strong class="text-dark"><?= Helper::timeAgo($a['created_at']) ?></strong></span>
                                </div>
                                <?php if (!empty($a['expires_at'])): ?>
                                    <div class="text-muted mt-1" style="font-size: 0.75rem;">
                                        Expires: <span class="font-monospace text-dark"><?= Helper::formatDateTime($a['expires_at'], 'd M, h:i A') ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <button type="button" 
                                    class="btn btn-outline-danger btn-sm rounded-pill btn-delete-alert fw-bold w-100 py-1" 
                                    data-alert-id="<?= $a['id'] ?>" 
                                    data-alert-title="<?= Helper::e($a['title']) ?>">
                                <i class="bi bi-trash3-fill me-1"></i> Revoke / Delete Alert
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div id="noActiveAlertsMsg" class="text-center py-5 <?= empty($activeAlertsList) ? '' : 'd-none' ?>">
                <div class="p-3 bg-success bg-opacity-10 text-success rounded-circle d-inline-flex mb-3">
                    <i class="bi bi-shield-check display-6"></i>
                </div>
                <h6 class="fw-bold text-dark mb-1">No Active Emergency Broadcasts</h6>
                <p class="text-muted small mb-0">All river watersheds are normal or previous test broadcasts have been revoked.</p>
            </div>
        </div>
    </div>

    <!-- Admin Shortcuts Row -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <a href="<?= BASE_URL ?>/views/admin/zones.php" class="card shadow-sm border-0 text-decoration-none text-dark p-3 rounded-3 text-center h-100 hover-card bg-white">
                <i class="bi bi-map-fill fs-2 text-primary mb-2"></i>
                <h6 class="fw-bold mb-1">Zone Management</h6>
                <small class="text-muted">Configure coordinates &amp; boundaries</small>
            </a>
        </div>
        <div class="col-md-3">
            <a href="<?= BASE_URL ?>/views/admin/risk_rules.php" class="card shadow-sm border-0 text-decoration-none text-dark p-3 rounded-3 text-center h-100 hover-card bg-white">
                <i class="bi bi-sliders fs-2 text-warning mb-2"></i>
                <h6 class="fw-bold mb-1">Risk Rules Engine</h6>
                <small class="text-muted">Adjust weights &amp; threshold cutoffs</small>
            </a>
        </div>
        <div class="col-md-3">
            <a href="<?= BASE_URL ?>/views/admin/camps.php" class="card shadow-sm border-0 text-decoration-none text-dark p-3 rounded-3 text-center h-100 hover-card bg-white">
                <i class="bi bi-hospital fs-2 text-success mb-2"></i>
                <h6 class="fw-bold mb-1">Camps Master Directory</h6>
                <small class="text-muted">Register and edit shelter locations</small>
            </a>
        </div>
        <div class="col-md-3">
            <a href="<?= BASE_URL ?>/views/admin/users.php" class="card shadow-sm border-0 text-decoration-none text-dark p-3 rounded-3 text-center h-100 hover-card bg-white">
                <i class="bi bi-people-fill fs-2 text-info mb-2"></i>
                <h6 class="fw-bold mb-1">User &amp; Staff Accounts</h6>
                <small class="text-muted">Manage coordinators &amp; credentials</small>
            </a>
        </div>
    </div>

    <!-- High Risk Zones & System Audit Logs -->
    <div class="row g-4">
        <!-- Top Flood Risk Zones -->
        <div class="col-lg-5">
            <div class="card shadow-sm border-0 rounded-4 h-100">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-dark mb-0"><i class="bi bi-shield-shaded text-danger me-2"></i>Highest Risk Watersheds</h6>
                    <a href="<?= BASE_URL ?>/views/admin/zones.php" class="small text-primary text-decoration-none">Manage All &rarr;</a>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php foreach ($zones as $z): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3 px-4">
                                <div>
                                    <strong class="text-dark d-block"><?= Helper::e($z['name']) ?></strong>
                                    <small class="text-muted"><?= Helper::e($z['district']) ?> &bull; <?= Helper::timeAgo($z['observed_at']) ?></small>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-<?= Helper::getRiskBadgeClass($z['category']) ?> fs-6 px-3 py-1">
                                        <?= Helper::e($z['category'] ?? 'Low') ?> (<?= (float)($z['score'] ?? 0) ?>)
                                    </span>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>

        <!-- System Audit Trail -->
        <div class="col-lg-7">
            <div class="card shadow-sm border-0 rounded-4 h-100">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-dark mb-0"><i class="bi bi-journal-text text-primary me-2"></i>Security &amp; Operations Audit Trail</h6>
                    <a href="<?= BASE_URL ?>/views/admin/audit_logs.php" class="small text-primary text-decoration-none">Full Audit Log &rarr;</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>Timestamp</th>
                                <th>Action Type</th>
                                <th>Actor</th>
                                <th>Event Details</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            <?php if (empty($recentLogs)): ?>
                                <tr><td colspan="4" class="text-center py-3 text-muted">No audit events recorded.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentLogs as $log): ?>
                                    <tr>
                                        <td class="font-monospace text-muted"><?= Helper::formatDateTime($log['created_at'], 'd M, h:i A') ?></td>
                                        <td><span class="badge bg-secondary font-monospace"><?= Helper::e($log['action_type']) ?></span></td>
                                        <td><?= Helper::e($log['full_name'] ?: 'System') ?></td>
                                        <td class="text-muted text-truncate" style="max-width: 250px;" title="<?= Helper::e($log['details']) ?>">
                                            <?= Helper::e($log['details']) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Notification Toast Container -->
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1090;">
    <div id="broadcastResultToast" class="toast align-items-center text-bg-dark border-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body d-flex align-items-center gap-2">
                <i id="toastBroadcastIcon" class="bi bi-check-circle-fill text-success fs-4"></i>
                <div>
                    <strong class="d-block text-white" id="toastBroadcastTitle">Alert Broadcast Dispatched</strong>
                    <span id="toastBroadcastMessage" class="small text-white-50"></span>
                </div>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<!-- Targeted Flood Red Alert Broadcast Modal -->
<div class="modal fade" id="targetedAlertModal" tabindex="-1" aria-labelledby="targetedAlertModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-danger text-white py-3 px-4">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-broadcast-pin fs-4"></i>
                    <div>
                        <h5 class="modal-title fw-bold mb-0" id="targetedAlertModalLabel">
                            Targeted Flood Red Alert Broadcast
                        </h5>
                        <small class="text-white-50">Geofenced early warning email notification &amp; shelter evacuation pipeline</small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="targetedAlertForm">
                <?= CSRF::inputField() ?>
                <div class="modal-body p-4">
                    <!-- Step 1: Waterway / Basin Selection -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="broadcastBasinSelect" class="form-label small fw-bold text-dark">
                                Target River Basin <span class="text-danger">*</span>
                            </label>
                            <select class="form-select form-select-sm" id="broadcastBasinSelect" name="basin_id" required>
                                <option value="">-- All / Select River Basin --</option>
                                <?php foreach ($allBasins as $ab): ?>
                                    <option value="<?= $ab['id'] ?>" <?= $ab['id'] == 1 ? 'selected' : '' ?>>
                                        <?= Helper::e($ab['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="broadcastDamSelect" class="form-label small fw-bold text-dark">
                                Target Dam / Reservoir <span class="text-danger">*</span>
                            </label>
                            <select class="form-select form-select-sm" id="broadcastDamSelect" name="dam_id" required>
                                <?php foreach ($allDams as $ad): ?>
                                    <option value="<?= $ad['id'] ?>" data-river="<?= $ad['river_id'] ?>" <?= $ad['id'] == 9 ? 'selected' : '' ?>>
                                        <?= Helper::e($ad['name']) ?> (<?= Helper::e($ad['district'] ?? 'Reservoir') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Live Recipient Counter & Shelter Routing Preview -->
                    <div class="p-3 bg-light rounded-4 border mb-3">
                        <div class="row g-3 align-items-center">
                            <div class="col-md-5">
                                <small class="text-muted text-uppercase fw-bold d-block" style="font-size: 0.72rem;">Targeted Citizens (Email Subscribers)</small>
                                <div class="d-flex align-items-baseline gap-2 mt-1">
                                    <span class="fs-4 fw-extrabold text-primary font-monospace" id="previewRecipientCount">1</span>
                                    <span class="small text-muted" id="previewRecipientLabel">citizens registered in watch zone</span>
                                </div>
                            </div>
                            <div class="col-md-7 border-start ps-md-4">
                                <small class="text-muted text-uppercase fw-bold d-block" style="font-size: 0.72rem;">Designated Primary Evacuation Center</small>
                                <div class="fw-bold text-dark mt-1 text-truncate" id="previewShelterName">
                                    Nashik Municipal Relief Shelter
                                </div>
                                <div class="small text-success fw-semibold" id="previewShelterCapacity">
                                    <i class="bi bi-shield-check me-1"></i> 390 available beds &bull; Incharge: Sunil Gaikwad
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Alert Details -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-8">
                            <label for="broadcastTitle" class="form-label small fw-bold text-dark">Alert Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="broadcastTitle" name="title" required 
                                   value="RED ALERT: Emergency Spillway Discharge & Inundation Warning">
                        </div>
                        <div class="col-md-4">
                            <label for="broadcastSeverity" class="form-label small fw-bold text-dark">Severity Level <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="broadcastSeverity" name="severity">
                                <option value="Critical Danger" selected>Critical Danger (Red Alert)</option>
                                <option value="Evacuation">Evacuation Order</option>
                                <option value="Warning">Warning (Orange Alert)</option>
                                <option value="Advisory">Advisory (Yellow Watch)</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="broadcastMessage" class="form-label small fw-bold text-dark">Advisory &amp; Evacuation Directive <span class="text-danger">*</span></label>
                        <textarea class="form-control form-control-sm" id="broadcastMessage" name="message" rows="3" required>Massive reservoir inflow detected from catchment area. Sluice and radial spillway discharge initiated. Citizens residing in low-lying floodplain zones must immediately evacuate to your designated primary relief shelter. Direct Google Maps navigation is attached below.</textarea>
                    </div>

                    <div class="p-2 bg-warning-subtle border border-warning-subtle rounded-3 small text-muted">
                        <i class="bi bi-info-circle-fill text-warning me-1"></i>
                        Emails are dispatched via the SMTP notification pipeline configured in <code>.env</code> with shelter routing coordinates &amp; rapid emergency helplines (112, 1077, 108).
                    </div>
                </div>
                <div class="modal-footer bg-light px-4 py-3 d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="btnDispatchAlert" class="btn btn-danger btn-sm rounded-pill px-4 fw-bold shadow-sm">
                        <i class="bi bi-send-fill me-2"></i> Dispatch Geofenced Red Alert
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const basinSelect = document.getElementById('broadcastBasinSelect');
    const damSelect = document.getElementById('broadcastDamSelect');
    const form = document.getElementById('targetedAlertForm');
    const btn = document.getElementById('btnDispatchAlert');

    async function updateBroadcastPreview() {
        const damId = damSelect?.value || '';
        const basinId = basinSelect?.value || '';

        try {
            const res = await fetch(`${window.APP_CONFIG.baseUrl}/api/broadcast.php?action=preview&dam_id=${encodeURIComponent(damId)}&basin_id=${encodeURIComponent(basinId)}`);
            const data = await res.json();
            if (data.success && data.data) {
                const count = data.data.targeted_count || 0;
                document.getElementById('previewRecipientCount').textContent = count;
                document.getElementById('previewRecipientLabel').textContent = count === 1 ? 'citizen registered in watch zone' : 'citizens registered in watch zone';

                if (data.data.shelter) {
                    const s = data.data.shelter;
                    document.getElementById('previewShelterName').textContent = s.display_name || s.name;
                    document.getElementById('previewShelterCapacity').innerHTML = `<i class="bi bi-shield-check me-1"></i> ${s.available_beds || 0} available beds &bull; Incharge: ${s.contact_person || 'Disaster Cell'}`;
                } else {
                    document.getElementById('previewShelterName').textContent = 'General Highland Disaster Shelter';
                    document.getElementById('previewShelterCapacity').innerHTML = `<i class="bi bi-shield-check me-1"></i> Available &bull; Incharge: District Officer`;
                }
            }
        } catch (err) {
            console.error('Failed to update broadcast preview:', err);
        }
    }

    if (damSelect) {
        damSelect.addEventListener('change', updateBroadcastPreview);
    }

    if (basinSelect) {
        basinSelect.addEventListener('change', () => {
            const basinId = basinSelect.value;
            // Filter dam select options
            Array.from(damSelect.options).forEach(opt => {
                const riverId = opt.getAttribute('data-river');
                if (!basinId || riverId === basinId || opt.value === '') {
                    opt.style.display = '';
                } else {
                    opt.style.display = 'none';
                }
            });
            // Pick first visible dam
            const firstVisible = Array.from(damSelect.options).find(opt => opt.style.display !== 'none');
            if (firstVisible) {
                damSelect.value = firstVisible.value;
            }
            updateBroadcastPreview();
        });
    }

    // Trigger initial preview
    updateBroadcastPreview();

    // Handle Form Submit
    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Dispatching Alert...';

        const payload = Object.fromEntries(new FormData(form).entries());

        try {
            const res = await fetch(`${window.APP_CONFIG.baseUrl}/api/broadcast.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const json = await res.json();
            if (json.success) {
                // Close modal
                const modalEl = document.getElementById('targetedAlertModal');
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();

                // Show toast
                const toastTitle = document.getElementById('toastBroadcastTitle');
                const toastMsg = document.getElementById('toastBroadcastMessage');
                const toastIcon = document.getElementById('toastBroadcastIcon');

                if (json.sent) {
                    toastTitle.textContent = 'Red Alert Dispatched Successfully!';
                    toastMsg.textContent = json.message || `Dispatched to ${json.data.dispatched_count} citizens via ${json.smtp_host_used}.`;
                    toastIcon.className = 'bi bi-check-circle-fill text-success fs-4';
                } else {
                    toastTitle.textContent = 'Alert Saved (SMTP Delivery Notice)';
                    toastMsg.textContent = `${json.error || 'SMTP delivery failed'}. Transcript logged to storage/logs/email.log`;
                    toastIcon.className = 'bi bi-exclamation-triangle-fill text-warning fs-4';
                }

                const toastEl = document.getElementById('broadcastResultToast');
                const toast = new bootstrap.Toast(toastEl, { delay: 8000 });
                toast.show();

                // Refresh alert list smoothly so the newly broadcasted alert appears
                setTimeout(() => location.reload(), 1500);
            } else {
                alert('Broadcast Notice: ' + json.message);
            }
        } catch (err) {
            alert('Request Failed: ' + err.message);
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-send-fill me-2"></i> Dispatch Geofenced Red Alert';
        }
    });

    // Revoke / Delete Emergency Alert AJAX Handler
    document.addEventListener('click', async (e) => {
        const deleteBtn = e.target.closest('.btn-delete-alert');
        if (!deleteBtn) return;

        const alertId = deleteBtn.getAttribute('data-alert-id');
        const alertTitle = deleteBtn.getAttribute('data-alert-title') || `Alert #${alertId}`;

        if (!confirm(`Are you sure you want to revoke and delete emergency alert #${alertId}?\n"${alertTitle}"\n\nThis will immediately remove the alert from public bulletins and citizen dashboards.`)) {
            return;
        }

        const originalHtml = deleteBtn.innerHTML;
        deleteBtn.disabled = true;
        deleteBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Revoking...';

        try {
            const res = await fetch(`${window.APP_CONFIG.baseUrl}/api/alerts.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    action: 'delete',
                    alert_id: alertId
                })
            });

            const json = await res.json();
            if (json.success) {
                // Smooth card removal animation
                const cardCol = document.getElementById(`alert-card-${alertId}`);
                if (cardCol) {
                    cardCol.style.transition = 'all 0.35s ease';
                    cardCol.style.opacity = '0';
                    cardCol.style.transform = 'scale(0.85)';
                    setTimeout(() => {
                        cardCol.remove();

                        const remaining = document.querySelectorAll('.alert-card-item').length;
                        const badge = document.getElementById('activeAlertsBadge');
                        if (badge) badge.textContent = `${remaining} ACTIVE`;
                        const metric = document.getElementById('metricActiveAlerts');
                        if (metric) metric.textContent = remaining;

                        if (remaining === 0) {
                            const grid = document.getElementById('activeAlertsGrid');
                            if (grid) grid.classList.add('d-none');
                            const emptyMsg = document.getElementById('noActiveAlertsMsg');
                            if (emptyMsg) emptyMsg.classList.remove('d-none');
                        }
                    }, 350);
                }

                // Show confirmation toast
                const toastTitle = document.getElementById('toastBroadcastTitle');
                const toastMsg = document.getElementById('toastBroadcastMessage');
                const toastIcon = document.getElementById('toastBroadcastIcon');

                if (toastTitle) toastTitle.textContent = 'Emergency Alert Revoked';
                if (toastMsg) toastMsg.textContent = `Alert #${alertId} was successfully deleted and removed from citizen bulletins.`;
                if (toastIcon) toastIcon.className = 'bi bi-check-circle-fill text-success fs-4';

                const toastEl = document.getElementById('broadcastResultToast');
                if (toastEl) {
                    const toast = new bootstrap.Toast(toastEl, { delay: 6000 });
                    toast.show();
                }
            } else {
                alert('Revocation Error: ' + (json.message || 'Failed to remove alert.'));
                deleteBtn.disabled = false;
                deleteBtn.innerHTML = originalHtml;
            }
        } catch (err) {
            alert('Request Failed: ' + err.message);
            deleteBtn.disabled = false;
            deleteBtn.innerHTML = originalHtml;
        }
    });
});
</script>

<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
