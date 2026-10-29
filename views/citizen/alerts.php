<?php
/**
 * Public Evacuation Bulletins & Emergency Alerts
 * Satisfies PRD Section 5.5 (FR-17 to FR-19)
 */

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once dirname(__DIR__, 2) . '/core/Database.php';
require_once dirname(__DIR__, 2) . '/core/Helper.php';
require_once dirname(__DIR__, 2) . '/services/AlertService.php';

$zoneFilter = !empty($_GET['zone_id']) ? (int)$_GET['zone_id'] : null;
$alerts = AlertService::getActiveAlerts($zoneFilter);

$db = Database::getInstance();
$zones = [];
if ($db->isConnected()) {
    $zones = $db->fetchAll("SELECT * FROM `zones` WHERE `status` = 'active' ORDER BY `name` ASC");
}

$pageTitle = "Active Emergency Alerts & Bulletins";
require_once dirname(__DIR__) . '/layouts/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="bi bi-broadcast text-danger me-2"></i>Official Evacuation &amp; Safety Bulletins
            </h2>
            <p class="text-muted small mb-0">Authorized disaster alerts issued directly by district disaster management authorities.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <label for="zoneSelect" class="small fw-semibold text-muted text-nowrap">Filter Zone:</label>
            <select id="zoneSelect" class="form-select form-select-sm" onchange="location.href = '?zone_id=' + this.value">
                <option value="">-- All Monitoring Regions --</option>
                <?php foreach ($zones as $z): ?>
                    <option value="<?= $z['id'] ?>" <?= $z['id'] == $zoneFilter ? 'selected' : '' ?>>
                        <?= Helper::e($z['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Active Alerts Feed -->
    <div class="row g-4">
        <div class="col-lg-8">
            <?php if (empty($alerts)): ?>
                <div class="card shadow-sm border-0 rounded-4 p-5 text-center bg-white">
                    <i class="bi bi-shield-check text-success display-4 mb-3"></i>
                    <h5 class="fw-bold text-dark">No Active Emergency Broadcasts</h5>
                    <p class="text-muted mb-0">All river basins in this region are currently within standard advisory thresholds.</p>
                </div>
            <?php else: ?>
                <?php foreach ($alerts as $alert): 
                    $isCritical = in_array($alert['severity'], ['Critical Danger', 'Evacuation']);
                    $borderClass = $isCritical ? 'border-danger' : 'border-warning';
                    $bgBadge = match($alert['severity']) {
                        'Critical Danger' => 'danger',
                        'Evacuation' => 'danger',
                        'Warning' => 'warning text-dark',
                        default => 'info text-dark'
                    };
                ?>
                    <div class="card shadow-sm border-2 <?= $borderClass ?> rounded-4 mb-4 overflow-hidden">
                        <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <span class="badge bg-<?= $bgBadge ?> fs-6 px-3 py-1">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= Helper::e($alert['severity']) ?>
                            </span>
                            <span class="small text-muted font-monospace">
                                Issued: <?= Helper::formatDateTime($alert['created_at']) ?> (<?= Helper::timeAgo($alert['created_at']) ?>)
                            </span>
                        </div>
                        <div class="card-body pt-0 px-4 pb-4">
                            <h4 class="fw-bold text-dark mb-2"><?= Helper::e($alert['title']) ?></h4>
                            <p class="text-muted mb-3" style="line-height: 1.6; font-size: 1.05rem;">
                                <?= nl2br(Helper::e($alert['message'])) ?>
                            </p>

                            <div class="p-3 bg-light rounded-3 d-flex justify-content-between align-items-center flex-wrap gap-2 border">
                                <div class="small">
                                    <span class="text-muted">Targeted Area:</span>
                                    <strong class="text-dark ms-1">
                                        <?= !empty($alert['zone_name']) ? Helper::e($alert['zone_name']) : 'All Monitored Regions' ?>
                                    </strong>
                                </div>
                                <div class="small">
                                    <span class="text-muted">Broadcast Channel:</span>
                                    <span class="badge bg-secondary ms-1"><?= Helper::e($alert['channel']) ?></span>
                                </div>
                                <?php if (!empty($alert['expires_at'])): ?>
                                    <div class="small text-muted">
                                        Valid Until: <span class="font-monospace"><?= Helper::formatDateTime($alert['expires_at']) ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Emergency Advisory Sidebar -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 rounded-4 mb-4 bg-primary text-white">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-2"><i class="bi bi-life-preserver me-2"></i>Need Immediate Evacuation?</h5>
                    <p class="small text-white-50 mb-3">
                        If you are in an evacuation zone, do not wait for waters to enter your premises. Move to designated relief centers or request urgent boat rescue.
                    </p>
                    <div class="d-grid gap-2">
                        <a href="<?= BASE_URL ?>/views/citizen/camps.php" class="btn btn-warning fw-bold">
                            <i class="bi bi-compass me-1"></i> Locate Nearest Relief Camp
                        </a>
                        <a href="<?= BASE_URL ?>/views/citizen/request_help.php" class="btn btn-light fw-bold text-danger">
                            <i class="bi bi-send-fill me-1"></i> Submit Rescue Request
                        </a>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="fw-bold text-dark mb-0"><i class="bi bi-telephone-fill text-danger me-2"></i>Emergency Operations Desks</h6>
                </div>
                <div class="card-body pt-0">
                    <ul class="list-group list-group-flush small">
                        <li class="list-group-item px-0 py-2 d-flex justify-content-between">
                            <span class="text-muted">National Disaster Helpline:</span>
                            <strong class="text-dark"><a href="tel:112">112</a></strong>
                        </li>
                        <li class="list-group-item px-0 py-2 d-flex justify-content-between">
                            <span class="text-muted">District Control Room:</span>
                            <strong class="text-dark"><a href="tel:1077">1077</a></strong>
                        </li>
                        <li class="list-group-item px-0 py-2 d-flex justify-content-between">
                            <span class="text-muted">Ambulance Services:</span>
                            <strong class="text-dark"><a href="tel:108">108</a></strong>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
