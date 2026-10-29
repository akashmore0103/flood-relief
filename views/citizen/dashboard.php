<?php
/**
 * Personalized Citizen Flood Watch Dashboard
 * Real-time dam tracking, 24-48h predictive forecast, and ticket history
 * Flood Risk Prediction & Relief Camp Locator
 */

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once dirname(__DIR__, 2) . '/core/Database.php';
require_once dirname(__DIR__, 2) . '/core/Auth.php';
require_once dirname(__DIR__, 2) . '/core/Helper.php';
require_once dirname(__DIR__, 2) . '/core/I18n.php';
require_once dirname(__DIR__, 2) . '/services/DamDataService.php';
require_once dirname(__DIR__, 2) . '/services/HelpRequestService.php';

$isAuth = Auth::check();
$userId = $isAuth ? Auth::id() : null;
$db = Database::getInstance();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Suppress bulky "Welcome back" flash banner to keep dashboard compact & clean
if (isset($_SESSION['flash_messages']) && is_array($_SESSION['flash_messages'])) {
    $_SESSION['flash_messages'] = array_values(array_filter($_SESSION['flash_messages'], function($f) {
        return !str_contains(strtolower($f['message'] ?? ''), 'welcome back');
    }));
}

// Retrieve or initialize Citizen Profile
$profile = null;
if ($userId && $db->isConnected()) {
    $profile = $db->fetchOne("
        SELECT cp.*, 
               cp.saved_village_city AS village_city,
               cp.saved_dam_id AS monitored_dam_id,
               cp.saved_river_id AS monitored_river_id,
               COALESCE(d.district, 'Nanded') AS district,
               COALESCE(d.state, 'Maharashtra') AS state,
               d.name AS dam_name, d.gross_capacity_tmc, d.current_storage_tmc, 
               d.live_storage_pct AS storage_percent, d.outflow_cusecs, d.inflow_cusecs, 
               d.gate_status AS flood_gate_status,
               18 AS gates_open_count,
               CASE 
                   WHEN d.live_storage_pct >= 90.0 OR d.outflow_cusecs >= 100000 THEN 'Critical'
                   WHEN d.live_storage_pct >= 75.0 OR d.outflow_cusecs >= 50000 THEN 'High'
                   ELSE 'Normal'
               END AS dam_alert_level,
               r.name AS river_name, 
               r.basin_name,
               r.current_level_meters AS current_water_level_m, 
               r.warning_level_meters AS warning_level_m, 
               r.danger_mark_meters AS danger_level_m, 
               r.status AS river_status
        FROM `citizen_profiles` cp
        LEFT JOIN `dams` d ON cp.saved_dam_id = d.id
        LEFT JOIN `rivers` r ON cp.saved_river_id = r.id
        WHERE cp.user_id = :uid
        LIMIT 1
    ", [':uid' => $userId]);
}

// Check session override (for instant live customization / guest mode)
if (!empty($_SESSION['guest_watch_profile'])) {
    $swp = $_SESSION['guest_watch_profile'];
    $selectedDamId = (int)($swp['monitored_dam_id'] ?? 1);
    $damInfo = DamDataService::getDamById($selectedDamId);
    if ($damInfo) {
        $profile = [
            'village_city' => $swp['village_city'] ?? $damInfo['district'],
            'district' => $swp['district'] ?? $damInfo['district'],
            'state' => $swp['state'] ?? $damInfo['state'],
            'monitored_dam_id' => $damInfo['id'],
            'monitored_river_id' => $damInfo['river_id'],
            'dam_name' => $damInfo['name'],
            'gross_capacity_tmc' => $damInfo['gross_capacity_tmc'],
            'current_storage_tmc' => $damInfo['current_storage_tmc'],
            'storage_percent' => $damInfo['storage_percent'],
            'outflow_cusecs' => $damInfo['outflow_cusecs'],
            'inflow_cusecs' => $damInfo['inflow_cusecs'],
            'flood_gate_status' => $damInfo['flood_gate_status'],
            'gates_open_count' => $damInfo['gates_open_count'] ?? 18,
            'dam_alert_level' => $damInfo['alert_level'],
            'river_name' => $damInfo['river_name'],
            'basin_name' => $damInfo['basin_name'] ?? 'Godavari Basin',
            'current_water_level_m' => $damInfo['river_current_level'],
            'warning_level_m' => $damInfo['river_warning_level'],
            'danger_level_m' => $damInfo['river_danger_level'],
            'river_status' => $damInfo['river_status']
        ];
    }
}

// Fallback baseline for non-logged-in guest demonstration
if (!$profile) {
    $defaultDam = DamDataService::getDamById(1) ?: [
        'id' => 1,
        'river_id' => 1,
        'name' => 'Jayakwadi Dam (Nath Sagar)',
        'gross_capacity_tmc' => 102.70,
        'current_storage_tmc' => 92.40,
        'storage_percent' => 89.97,
        'outflow_cusecs' => 42000,
        'inflow_cusecs' => 38500,
        'flood_gate_status' => '18 Gates Opened at 1.5 ft',
        'alert_level' => 'High',
        'river_name' => 'Godavari River',
        'basin_name' => 'Godavari Basin',
        'river_current_level' => 15.35,
        'river_warning_level' => 14.50,
        'river_danger_level' => 16.20,
        'river_status' => 'Warning',
        'district' => 'Chhatrapati Sambhajinagar',
        'state' => 'Maharashtra'
    ];

    $profile = [
        'village_city' => 'Nanded City (Godavari Floodplain)',
        'district' => $defaultDam['district'] ?? 'Nanded',
        'state' => $defaultDam['state'] ?? 'Maharashtra',
        'monitored_dam_id' => $defaultDam['id'],
        'monitored_river_id' => $defaultDam['river_id'],
        'dam_name' => $defaultDam['name'],
        'gross_capacity_tmc' => $defaultDam['gross_capacity_tmc'],
        'current_storage_tmc' => $defaultDam['current_storage_tmc'],
        'storage_percent' => $defaultDam['storage_percent'],
        'outflow_cusecs' => $defaultDam['outflow_cusecs'],
        'inflow_cusecs' => $defaultDam['inflow_cusecs'],
        'flood_gate_status' => $defaultDam['flood_gate_status'],
        'gates_open_count' => 18,
        'dam_alert_level' => $defaultDam['alert_level'],
        'river_name' => $defaultDam['river_name'],
        'basin_name' => $defaultDam['basin_name'] ?? 'Godavari Basin',
        'current_water_level_m' => $defaultDam['river_current_level'],
        'warning_level_m' => $defaultDam['river_warning_level'],
        'danger_level_m' => $defaultDam['river_danger_level'],
        'river_status' => $defaultDam['river_status']
    ];
}

// All dams and rivers for bookmark modal
$allDams = DamDataService::getAllDams();
$allRivers = DamDataService::getAllRivers();

// Citizen Distress Request History
$myRequests = $userId ? HelpRequestService::getUserRequests($userId) : [];

// Retrieve primary designated relief shelter for this monitored dam / river basin
$monitoredDamId = (int)($profile['monitored_dam_id'] ?? 1);
$monitoredRiverId = (int)($profile['monitored_river_id'] ?? 1);
$primaryShelter = null;

if ($db->isConnected()) {
    $primaryShelter = $db->fetchOne("
        SELECT c.*, 
               c.id AS camp_id,
               COALESCE(c.camp_name, c.name) AS display_name,
               (c.total_capacity - c.current_occupancy) AS available_beds,
               d.name AS dam_name,
               d.district AS dam_district,
               r.name AS river_name,
               r.basin_name
        FROM `camps` c
        LEFT JOIN `dams` d ON c.dam_id = d.id
        LEFT JOIN `rivers` r ON (c.river_basin_id = r.id OR d.river_id = r.id)
        WHERE c.dam_id = :dam_id AND c.active = TRUE
        ORDER BY c.total_capacity DESC
        LIMIT 1
    ", [':dam_id' => $monitoredDamId]);

    if (!$primaryShelter && $monitoredRiverId) {
        $primaryShelter = $db->fetchOne("
            SELECT c.*, 
                   c.id AS camp_id,
                   COALESCE(c.camp_name, c.name) AS display_name,
                   (c.total_capacity - c.current_occupancy) AS available_beds,
                   d.name AS dam_name,
                   r.name AS river_name,
                   r.basin_name
            FROM `camps` c
            LEFT JOIN `dams` d ON c.dam_id = d.id
            LEFT JOIN `rivers` r ON (c.river_basin_id = r.id OR d.river_id = r.id)
            WHERE (c.river_basin_id = :basin_id OR d.river_id = :basin_id_alt) AND c.active = TRUE
            ORDER BY c.total_capacity DESC
            LIMIT 1
        ", [':basin_id' => $monitoredRiverId, ':basin_id_alt' => $monitoredRiverId]);
    }
}

if (!$primaryShelter) {
    $primaryShelter = [
        'id' => 14,
        'camp_name' => 'Nashik Municipal Relief Shelter',
        'display_name' => 'Nashik Municipal Relief Shelter',
        'address' => 'Near Gangapur Dam Road, Anandwalli, Nashik, Maharashtra',
        'district' => $profile['district'] ?? 'Nashik',
        'latitude' => 19.9985000,
        'longitude' => 73.7250000,
        'contact_person' => 'Sunil Gaikwad (Tehsildar / Relief Incharge)',
        'contact_phone' => '+91 94231 88765',
        'total_capacity' => 600,
        'current_occupancy' => 210,
        'available_beds' => 390,
        'status' => 'Operational',
        'supply_status' => 'Sufficient',
        'dam_name' => $profile['dam_name'] ?? 'Gangapur Dam (Nashik)'
    ];
}

// 24-to-48h Predictive Flood Forecast Algorithm
$damOutflow = (float)$profile['outflow_cusecs'];
$storagePct = (float)$profile['storage_percent'];
$riverLevel = (float)$profile['current_water_level_m'];
$dangerLevel = (float)$profile['danger_level_m'];

// Compute composite forecast score (0 - 100)
$forecastScore = 0;
$forecastScore += min(40, ($storagePct / 100) * 40);
$forecastScore += min(35, ($damOutflow / 100000) * 35);
$forecastScore += min(25, ($riverLevel / max(1, $dangerLevel)) * 25);
$forecastScore = round($forecastScore, 1);

if ($forecastScore >= 80) {
    $predLevel = 'Critical Evacuation Alert';
    $predBadge = 'bg-danger pulse-emergency';
    $predMsg = 'Extreme 24h risk: Dam discharge exceeding safe threshold. Downstream floodplain inundation imminent.';
    $actionRec = 'Move to designated relief shelter immediately. Do not attempt to cross flooded roads or causeways.';
} elseif ($forecastScore >= 55) {
    $predLevel = 'Flood Advisory / Watch';
    $predBadge = 'bg-warning text-dark';
    $predMsg = 'Moderate-to-High surge expected within 24-48 hours. River water levels approaching warning marks.';
    $actionRec = 'Keep emergency documents, medicines, and power banks sealed. Secure livestock to higher grounds.';
} else {
    $predLevel = 'Safe / Normal Flow';
    $predBadge = 'bg-success';
    $predMsg = 'Hydrological conditions stable. Dam storage within standard conservation limits.';
    $actionRec = 'No immediate flood threat detected in your local watershed. Normal monitoring maintained.';
}

$pageTitle = "Citizen Flood Watch Dashboard";
require_once dirname(__DIR__) . '/layouts/header.php';
?>

<style>
/* Emergency SOS High-Visibility Pulsing Animation */
@keyframes pulse-emergency-glow {
    0% {
        box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.7);
        transform: scale(1);
    }
    50% {
        box-shadow: 0 0 0 10px rgba(220, 53, 69, 0);
        transform: scale(1.02);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(220, 53, 69, 0);
        transform: scale(1);
    }
}
.pulse-emergency-btn {
    animation: pulse-emergency-glow 1.8s infinite;
}
.pulse-emergency-btn:hover {
    animation: none;
    box-shadow: 0 0 15px rgba(220, 53, 69, 0.6) !important;
}
</style>

<div class="container-fluid px-lg-4 py-3">
    <!-- Notification Toast Container -->
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1080;">
        <div id="liveWatchToast" class="toast align-items-center text-bg-dark border-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body d-flex align-items-center gap-2">
                    <i class="bi bi-check-circle-fill text-success fs-5"></i>
                    <span id="toastMessage">Watch settings updated successfully.</span>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>

    <!-- MODULE 1: Compact Greeting & Action Header -->
    <div class="card glass-panel border-0 shadow-sm p-4" style="border-radius: 16px; margin-bottom: 24px !important;">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
            <div>
                <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                    <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1 font-monospace fw-semibold" style="font-size: 0.75rem;">
                        <i class="bi bi-shield-check me-1"></i> ACTIVE FLOOD WATCH
                    </span>
                    <span id="headerLocationBadge" class="badge bg-dark-subtle text-dark border border-secondary-subtle rounded-pill px-3 py-1 font-monospace" style="font-size: 0.75rem;">
                        <i class="bi bi-geo-alt-fill text-danger me-1"></i> <span id="headerLocText"><?= Helper::e($profile['village_city']) ?>, <?= Helper::e($profile['district']) ?></span>
                    </span>
                    <span id="headerDamBadge" class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-1 font-monospace" style="font-size: 0.75rem;">
                        <i class="bi bi-moisture me-1"></i> <span id="headerDamText"><?= Helper::e($profile['dam_name']) ?></span>
                    </span>
                </div>
                <h2 class="fw-bold text-dark mb-1" style="font-size: 1.6rem;">
                    <?= $isAuth ? 'Namaste, ' . Helper::e(Auth::user()['full_name']) : 'Namaste, Citizen' ?>
                </h2>
                <p class="text-muted mb-0 small">
                    Personalized hydrological decision support monitoring <strong id="headerDamNameDesc"><?= Helper::e($profile['dam_name']) ?></strong> on the <strong id="headerRiverNameDesc"><?= Helper::e($profile['river_name']) ?></strong> basin.
                </p>
            </div>
            <div class="d-flex align-items-center gap-2 flex-shrink-0">
                <button class="btn btn-outline-dark btn-sm rounded-pill px-3 py-2 shadow-sm d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#bookmarkModal">
                    <i class="bi bi-sliders me-2"></i> Edit Monitored Flood Watch
                </button>
                <a href="<?= BASE_URL ?>/views/citizen/request_help.php" class="btn btn-danger btn-sm rounded-pill px-3 py-2 shadow-sm fw-bold d-flex align-items-center pulse-emergency-btn">
                    <i class="bi bi-life-preserver me-2"></i> Request Emergency SOS
                </a>
            </div>
        </div>
    </div>

    <!-- Telemetry Cards Grid -->
    <div class="row g-4 mb-4">
        <!-- 24-48h Predictive Forecast Card -->
        <div class="col-lg-4">
            <div class="card glass-card h-100 border-0 shadow-sm rounded-4">
                <div class="card-header bg-transparent border-0 pt-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold text-dark mb-0">
                            <i class="bi bi-radar text-primary me-2"></i>24-48h Predictive Forecast
                        </h6>
                        <span id="forecastBadge" class="badge <?= $predBadge ?> px-3 py-1 rounded-pill"><?= $predLevel ?></span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="p-3 rounded-4 bg-light text-center mb-3 border">
                        <div class="small text-muted text-uppercase fw-bold">Composite Flood Risk Index</div>
                        <div id="forecastScoreVal" class="display-5 fw-extrabold text-dark my-1 font-monospace"><?= $forecastScore ?> <small class="fs-6 text-muted">/ 100</small></div>
                        <div class="progress" style="height: 8px;">
                            <div id="forecastProgressBar" class="progress-bar <?= $forecastScore >= 80 ? 'bg-danger' : ($forecastScore >= 55 ? 'bg-warning' : 'bg-success') ?>" 
                                 style="width: <?= $forecastScore ?>%;"></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <h6 class="fw-bold text-dark small mb-1"><i class="bi bi-info-circle text-primary me-1"></i> Forecast Summary:</h6>
                        <p id="forecastSummaryText" class="text-muted small mb-0"><?= $predMsg ?></p>
                    </div>

                    <div class="p-3 rounded-3 bg-warning-subtle border border-warning-subtle">
                        <h6 class="fw-bold text-dark small mb-1"><i class="bi bi-shield-fill-exclamation text-warning me-1"></i> Action Recommendation:</h6>
                        <p id="forecastActionText" class="small text-dark mb-0"><?= $actionRec ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Primary Dam Telemetry Gauge Card -->
        <div class="col-lg-4">
            <?php 
                $damPct = (float)$profile['storage_percent'];
                $damStroke = '#10b981';
                if ($damPct >= 90.0) $damStroke = '#ef4444';
                elseif ($damPct >= 75.0) $damStroke = '#f59e0b';
                $damOffset = 327 - (327 * ($damPct / 100));
            ?>
            <div class="card glass-card h-100 border-0 shadow-sm rounded-4 text-center">
                <div class="card-header bg-transparent border-0 pt-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-dark mb-0">
                        <i class="bi bi-moisture text-info me-2"></i>Monitored Dam Reservoir
                    </h6>
                    <span id="damCardHeaderBadge" class="badge bg-secondary-subtle text-secondary rounded-pill small"><?= Helper::e($profile['dam_name']) ?></span>
                </div>
                <div class="card-body py-2">
                    <!-- Gauge -->
                    <div class="circular-gauge-box my-2">
                        <svg width="140" height="140" viewBox="0 0 140 140">
                            <circle class="gauge-bg" cx="70" cy="70" r="52"></circle>
                            <circle id="damGaugeCircle" class="gauge-fill" cx="70" cy="70" r="52" 
                                    stroke="<?= $damStroke ?>"
                                    stroke-dasharray="327" 
                                    stroke-dashoffset="<?= $damOffset ?>"></circle>
                        </svg>
                        <div class="gauge-text">
                            <div id="damGaugeVal" class="gauge-val" style="color: <?= $damStroke ?>;"><?= round($damPct) ?>%</div>
                            <div class="gauge-lbl" data-i18n="dam_storage">Capacity</div>
                        </div>
                    </div>

                    <div class="row g-2 text-start mt-2">
                        <div class="col-6">
                            <div class="p-2 bg-light rounded-3">
                                <small class="text-muted d-block" style="font-size: 0.72rem;">Current Storage</small>
                                <strong id="damStorageVal" class="text-dark"><?= number_format($profile['current_storage_tmc'], 2) ?></strong>
                                <span id="damGrossCapVal" class="text-muted" style="font-size: 0.75rem;">/ <?= $profile['gross_capacity_tmc'] ?> TMC</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 bg-light rounded-3">
                                <small class="text-muted d-block" style="font-size: 0.72rem;">Outflow Discharge</small>
                                <strong id="damOutflowVal" class="<?= (float)$profile['outflow_cusecs'] > 50000 ? 'text-danger' : 'text-primary' ?>">
                                    <?= number_format($profile['outflow_cusecs']) ?>
                                </strong>
                                <span class="text-muted" style="font-size: 0.75rem;">cusecs</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 bg-light rounded-3">
                                <small class="text-muted d-block" style="font-size: 0.72rem;">Flood Gates</small>
                                <strong id="damGateStatusVal"><?= Helper::e($profile['flood_gate_status']) ?></strong>
                                <span id="damGateCountVal" class="text-muted" style="font-size: 0.75rem;">(<?= (int)$profile['gates_open_count'] ?>)</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 bg-light rounded-3">
                                <small class="text-muted d-block" style="font-size: 0.72rem;">Alert Level</small>
                                <strong id="damAlertLevelVal" class="<?= $profile['dam_alert_level'] === 'Critical' ? 'text-danger' : 'text-success' ?>">
                                    <?= Helper::e($profile['dam_alert_level']) ?>
                                </strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- River Danger Level Card -->
        <div class="col-lg-4">
            <div class="card glass-card h-100 border-0 shadow-sm rounded-4">
                <div class="card-header bg-transparent border-0 pt-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-dark mb-0">
                        <i class="bi bi-water text-primary me-2"></i>River Danger Mark
                    </h6>
                    <span id="riverCardHeaderBadge" class="badge bg-secondary-subtle text-secondary rounded-pill small"><?= Helper::e($profile['river_name']) ?></span>
                </div>
                <div class="card-body">
                    <div class="p-3 bg-light rounded-4 mb-3 text-center border">
                        <small class="text-muted text-uppercase fw-bold">Live Water Gauge</small>
                        <div id="riverCurrentLevelVal" class="display-5 fw-extrabold text-dark my-1 font-monospace">
                            <?= number_format($profile['current_water_level_m'], 2) ?>m
                        </div>
                        <span id="riverStatusBadge" class="badge <?= $profile['river_status'] === 'Flooding' ? 'bg-danger' : 'bg-success' ?> px-3 py-1 rounded-pill">
                            Status: <?= Helper::e($profile['river_status']) ?>
                        </span>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted">Warning Mark: <strong id="riverWarningVal"><?= number_format($profile['warning_level_m'], 1) ?>m</strong></span>
                            <span class="text-danger fw-bold">Danger Mark: <strong id="riverDangerVal"><?= number_format($profile['danger_level_m'], 1) ?>m</strong></span>
                        </div>
                        <?php 
                            $rCurr = (float)$profile['current_water_level_m'];
                            $rDang = (float)$profile['danger_level_m'];
                            $rBarWidth = min(100, round(($rCurr / max(1, $rDang)) * 100));
                        ?>
                        <div class="progress" style="height: 10px;">
                            <div id="riverProgressBar" class="progress-bar <?= $rCurr >= $rDang ? 'bg-danger' : 'bg-primary' ?>" style="width: <?= $rBarWidth ?>%;"></div>
                        </div>
                    </div>

                    <div class="d-grid mt-4">
                        <a href="<?= BASE_URL ?>/views/dams_view.php" class="btn btn-outline-primary btn-sm rounded-pill">
                            <i class="bi bi-moisture me-1"></i> View All River Basins &amp; Reservoirs
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Designated Primary Emergency Relief Shelter & Evacuation Map Card -->
    <div class="card glass-card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-dark text-white py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-shield-fill-plus text-danger fs-5"></i>
                <div>
                    <h5 class="fw-bold mb-0 text-white">
                        Designated Emergency Shelter for <span id="shelterDamTitle" class="text-info"><?= Helper::e($profile['dam_name']) ?></span>
                    </h5>
                    <small class="text-white-50">Pre-assigned emergency evacuation shelter linked to your monitored flood basin</small>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span id="shelterSupplyBadge" class="badge bg-<?= ($primaryShelter['supply_status'] ?? 'Sufficient') === 'Abundant' ? 'success' : (($primaryShelter['supply_status'] ?? '') === 'Critical' ? 'danger' : 'primary') ?> px-3 py-2 rounded-pill font-monospace">
                    <i class="bi bi-box-seam me-1"></i> Supply Status: <?= Helper::e($primaryShelter['supply_status'] ?? 'Sufficient') ?>
                </span>
                <a id="btnAllShelters" href="<?= BASE_URL ?>/views/citizen/camps.php?dam_id=<?= (int)$profile['monitored_dam_id'] ?>" class="btn btn-outline-light btn-sm rounded-pill px-3">
                    <i class="bi bi-grid me-1"></i> View All Camps
                </a>
            </div>
        </div>
        <div class="card-body p-4">
            <div class="row g-4 align-items-center">
                <!-- Shelter Details Column -->
                <div class="col-lg-5">
                    <div class="p-3 bg-light rounded-4 border mb-3">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <span class="badge bg-secondary-subtle text-secondary border small mb-1" id="shelterDistrictBadge">
                                    <i class="bi bi-geo-alt me-1"></i><?= Helper::e($primaryShelter['district'] ?? $profile['district']) ?>
                                </span>
                                <h5 class="fw-bold text-dark mb-0" id="shelterNameHeading">
                                    <?= Helper::e($primaryShelter['display_name'] ?? 'Primary Relief Shelter') ?>
                                </h5>
                            </div>
                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-1" id="shelterStatusBadge">
                                <?= Helper::e($primaryShelter['status'] ?? 'Operational') ?>
                            </span>
                        </div>
                        <p class="small text-muted mb-3" id="shelterAddressText">
                            <?= Helper::e($primaryShelter['address'] ?? 'Safe Highland Evacuation Zone') ?>
                        </p>

                        <!-- Bed Capacity Stats -->
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <div class="p-2 bg-white rounded-3 border text-center">
                                    <small class="text-muted d-block" style="font-size: 0.72rem;">Available Beds</small>
                                    <span class="fs-4 fw-extrabold text-success font-monospace" id="shelterAvailableBeds">
                                        <?= (int)($primaryShelter['available_beds'] ?? 390) ?>
                                    </span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2 bg-white rounded-3 border text-center">
                                    <small class="text-muted d-block" style="font-size: 0.72rem;">Total Capacity</small>
                                    <span class="fs-4 fw-extrabold text-dark font-monospace" id="shelterTotalCapacity">
                                        <?= (int)($primaryShelter['total_capacity'] ?? 600) ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Officer Contact -->
                        <div class="d-flex align-items-center justify-content-between p-2 bg-white rounded-3 border mb-3">
                            <div class="d-flex align-items-center gap-2">
                                <div class="p-2 bg-primary-subtle text-primary rounded-circle">
                                    <i class="bi bi-person-fill fs-5"></i>
                                </div>
                                <div>
                                    <small class="text-muted d-block" style="font-size: 0.72rem;">Shelter Incharge / Officer</small>
                                    <strong class="text-dark small" id="shelterContactPerson"><?= Helper::e($primaryShelter['contact_person'] ?? 'Disaster Relief Cell') ?></strong>
                                </div>
                            </div>
                            <a id="shelterCallBtn" href="tel:<?= Helper::e($primaryShelter['contact_phone'] ?? '+919423188765') ?>" class="btn btn-outline-success btn-sm rounded-pill px-3">
                                <i class="bi bi-telephone-fill me-1"></i> <span id="shelterContactPhone"><?= Helper::e($primaryShelter['contact_phone'] ?? '+91 94231 88765') ?></span>
                            </a>
                        </div>

                        <!-- Direct Directions Action -->
                        <div class="d-grid">
                            <a id="shelterDirectionsBtn" 
                               href="https://www.google.com/maps/dir/?api=1&destination=<?= $primaryShelter['latitude'] ?? '19.9985' ?>,<?= $primaryShelter['longitude'] ?? '73.7250' ?>" 
                               target="_blank" 
                               class="btn btn-danger btn-lg rounded-pill fw-bold shadow-sm d-flex align-items-center justify-content-center gap-2">
                                <i class="bi bi-sign-turn-right-fill fs-5"></i> Get Navigation Directions
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Interactive Leaflet Map Column -->
                <div class="col-lg-7">
                    <div class="rounded-4 overflow-hidden border shadow-sm position-relative">
                        <div id="primaryShelterMap" style="height: 380px; width: 100%;"></div>
                        <div class="position-absolute bottom-0 start-0 m-3 px-3 py-1 bg-dark bg-opacity-75 text-white rounded-pill small z-1" style="font-size: 0.75rem;">
                            <i class="bi bi-compass me-1 text-danger"></i> Evacuation Route Active &bull; Leaflet Live Telemetry
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- My Submitted Help Requests Section -->
    <div class="card glass-card border-0 shadow-sm rounded-4 overflow-hidden mb-4">
        <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2 d-flex justify-content-between align-items-center">
            <div>
                <h5 class="fw-bold text-dark mb-0">
                    <i class="bi bi-clock-history text-primary me-2"></i>My Emergency Requests History
                </h5>
                <small class="text-muted">Status and response tracking of your submitted distress calls</small>
            </div>
            <a href="<?= BASE_URL ?>/views/citizen/request_help.php" class="btn btn-outline-danger btn-sm rounded-pill px-3">
                <i class="bi bi-plus-circle me-1"></i> New Help Request
            </a>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small">
                        <tr>
                            <th class="ps-4">Reference Code</th>
                            <th>Category</th>
                            <th>Urgency</th>
                            <th>Water Severity</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php if (empty($myRequests)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-2 text-muted d-block mb-2"></i>
                                    No assistance tickets submitted yet. If you are experiencing flood emergencies, submit a request above.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($myRequests as $req): ?>
                                <tr>
                                    <td class="ps-4 font-monospace fw-bold text-primary">
                                        <?= Helper::e($req['reference_code']) ?>
                                    </td>
                                    <td><?= Helper::e($req['request_type']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= Helper::getUrgencyBadgeClass($req['urgency']) ?> rounded-pill">
                                            <?= Helper::e($req['urgency']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-info-subtle text-info border border-info-subtle rounded-pill">
                                            <?= Helper::e($req['water_severity'] ?? 'Waist-Level') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= Helper::getStatusBadgeClass($req['status']) ?> rounded-pill">
                                            <?= Helper::e($req['status']) ?>
                                        </span>
                                    </td>
                                    <td class="text-muted"><?= Helper::timeAgo($req['created_at']) ?></td>
                                    <td>
                                        <a href="<?= BASE_URL ?>/views/citizen/track_request.php?ref=<?= urlencode($req['reference_code']) ?>" 
                                           class="btn btn-sm btn-outline-primary rounded-pill px-3">
                                            <i class="bi bi-search me-1"></i> Track
                                        </a>
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

<!-- MODULE 2: 3-TIER CASCADING LOCATION & DAM TELEMETRY SELECTOR MODAL -->
<div class="modal fade" id="bookmarkModal" tabindex="-1" aria-labelledby="bookmarkModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-dark text-white py-3 px-4">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-sliders text-warning fs-5"></i>
                    <h5 class="modal-title fw-bold mb-0" id="bookmarkModalLabel">
                        Customize Monitored Flood Watch
                    </h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="bookmarkForm">
                <?= CSRF::inputField() ?>
                <div class="modal-body p-4">
                    <p class="text-muted small mb-3">
                        Select your State, River Basin, and Primary Dam to calibrate your localized early flood warning telemetry and gauge thresholds.
                    </p>
                    
                    <div class="row g-3">
                        <!-- Step 1: Select State -->
                        <div class="col-md-4">
                            <label for="selectState" class="form-label fw-semibold small text-dark">
                                <span class="badge bg-primary me-1">1</span> Select State *
                            </label>
                            <select class="form-select form-select-sm" id="selectState" name="state" required>
                                <option value="">-- Choose State --</option>
                            </select>
                        </div>

                        <!-- Step 2: River Basin (Dependent on State) -->
                        <div class="col-md-4">
                            <label for="selectBasin" class="form-label fw-semibold small text-dark">
                                <span class="badge bg-primary me-1">2</span> River Basin *
                            </label>
                            <select class="form-select form-select-sm" id="selectBasin" name="basin" required disabled>
                                <option value="">-- Select State First --</option>
                            </select>
                        </div>

                        <!-- Step 3: Primary Dam (Dependent on Basin) -->
                        <div class="col-md-4">
                            <label for="selectDam" class="form-label fw-semibold small text-dark">
                                <span class="badge bg-primary me-1">3</span> Primary Dam / Reservoir *
                            </label>
                            <select class="form-select form-select-sm" id="selectDam" name="monitored_dam_id" required disabled>
                                <option value="">-- Select Basin First --</option>
                            </select>
                        </div>
                    </div>

                    <!-- Step 4: Locality / Ward / District -->
                    <div class="card bg-light border-0 rounded-3 p-3 mt-3">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="village_city" class="form-label fw-semibold small text-dark">
                                    <span class="badge bg-secondary me-1">4</span> Locality / Ward / Village *
                                </label>
                                <input type="text" class="form-control form-control-sm" id="village_city" name="village_city" required 
                                       value="<?= Helper::e($profile['village_city']) ?>" placeholder="E.g., Nanded City, Ward 4 or Sangli Old Bridge">
                            </div>
                            <div class="col-md-6">
                                <label for="district" class="form-label fw-semibold small text-dark">
                                    District *
                                </label>
                                <input type="text" class="form-control form-control-sm" id="district" name="district" required 
                                       value="<?= Helper::e($profile['district']) ?>" placeholder="Auto-populated with Dam District">
                            </div>
                        </div>
                    </div>

                    <!-- Live Dam Preview Snapshot Box -->
                    <div id="damPreviewBox" class="mt-3 p-3 rounded-3 border bg-white shadow-sm" style="display: none;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="small fw-bold text-primary"><i class="bi bi-info-circle me-1"></i> Live Telemetry Benchmark:</span>
                            <span id="previewAlertBadge" class="badge bg-success rounded-pill">Normal</span>
                        </div>
                        <div class="row g-2 text-center small">
                            <div class="col-3">
                                <div class="p-2 bg-light rounded-3">
                                    <span class="text-muted d-block" style="font-size:0.7rem;">Live Storage</span>
                                    <strong id="previewStoragePct" class="text-dark">0%</strong>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="p-2 bg-light rounded-3">
                                    <span class="text-muted d-block" style="font-size:0.7rem;">Gross Capacity</span>
                                    <strong id="previewCapacity" class="text-dark">0 TMC</strong>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="p-2 bg-light rounded-3">
                                    <span class="text-muted d-block" style="font-size:0.7rem;">Outflow</span>
                                    <strong id="previewOutflow" class="text-dark">0 cusecs</strong>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="p-2 bg-light rounded-3">
                                    <span class="text-muted d-block" style="font-size:0.7rem;">Danger Mark</span>
                                    <strong id="previewDanger" class="text-danger">0m</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 px-4">
                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="btnSaveBookmarks" class="btn btn-primary btn-sm rounded-pill fw-semibold px-4">
                        <i class="bi bi-check2-circle me-1"></i> Save Watch Settings
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- PRELOADED 3-TIER CASCADE DATASET & CONTROLLER SCRIPT -->
<script>
const FLOOD_WATCH_DATASET = {
    "Maharashtra": {
        "Godavari Basin": [
            {
                id: 1,
                name: "Jayakwadi Dam (Nath Sagar)",
                river_id: 1,
                river_name: "Godavari River",
                district: "Chhatrapati Sambhajinagar",
                locality: "Nanded City (Godavari Floodplain)",
                gross_capacity_tmc: 102.70,
                current_storage_tmc: 92.40,
                storage_percent: 89.97,
                water_level_m: 463.50,
                fsl_m: 463.90,
                inflow_cusecs: 38500,
                outflow_cusecs: 42000,
                flood_gate_status: "18 Gates Opened at 1.5 ft",
                gates_open_count: 18,
                river_current_level: 15.35,
                river_warning_level: 14.50,
                river_danger_level: 16.20,
                river_status: "Warning",
                alert_level: "High"
            },
            {
                id: 9,
                name: "Gangapur Dam (Nashik)",
                river_id: 1,
                river_name: "Godavari River",
                district: "Nashik",
                locality: "Gangapur / Nashik West",
                gross_capacity_tmc: 5.63,
                current_storage_tmc: 5.20,
                storage_percent: 92.36,
                water_level_m: 611.20,
                fsl_m: 612.00,
                inflow_cusecs: 8400,
                outflow_cusecs: 12000,
                flood_gate_status: "4 Radial Gates Open",
                gates_open_count: 4,
                river_current_level: 14.80,
                river_warning_level: 14.00,
                river_danger_level: 15.50,
                river_status: "Warning",
                alert_level: "Critical"
            },
            {
                id: 10,
                name: "Nandur Madhmeshwar Weir",
                river_id: 1,
                river_name: "Godavari River",
                district: "Nashik",
                locality: "Niphad / Madhmeshwar",
                gross_capacity_tmc: 1.25,
                current_storage_tmc: 1.15,
                storage_percent: 92.00,
                water_level_m: 532.00,
                fsl_m: 532.50,
                inflow_cusecs: 35000,
                outflow_cusecs: 38000,
                flood_gate_status: "16 Gates Open",
                gates_open_count: 16,
                river_current_level: 15.10,
                river_warning_level: 14.20,
                river_danger_level: 15.80,
                river_status: "Warning",
                alert_level: "Critical"
            },
            {
                id: 11,
                name: "Bhandardara Dam (Ahilyanagar)",
                river_id: 1,
                river_name: "Pravara River (Godavari Sub-basin)",
                district: "Ahilyanagar",
                locality: "Akole / Bhandardara Lake",
                gross_capacity_tmc: 11.04,
                current_storage_tmc: 10.45,
                storage_percent: 94.65,
                water_level_m: 745.80,
                fsl_m: 746.50,
                inflow_cusecs: 6200,
                outflow_cusecs: 7500,
                flood_gate_status: "Spillway Discharging",
                gates_open_count: 2,
                river_current_level: 13.90,
                river_warning_level: 13.50,
                river_danger_level: 15.00,
                river_status: "Warning",
                alert_level: "Critical"
            },
            {
                id: 12,
                name: "Nilwande Dam (Pravara Basin)",
                river_id: 1,
                river_name: "Pravara River (Godavari Sub-basin)",
                district: "Ahilyanagar",
                locality: "Sangamner / Akole Range",
                gross_capacity_tmc: 8.32,
                current_storage_tmc: 7.15,
                storage_percent: 85.94,
                water_level_m: 642.10,
                fsl_m: 643.00,
                inflow_cusecs: 4500,
                outflow_cusecs: 5200,
                flood_gate_status: "2 Sluice Gates Open",
                gates_open_count: 2,
                river_current_level: 12.80,
                river_warning_level: 13.00,
                river_danger_level: 14.50,
                river_status: "Normal",
                alert_level: "High"
            }
        ],
        "Krishna Basin": [
            {
                id: 2,
                name: "Koyna Dam (Shivsagar Lake)",
                river_id: 2,
                river_name: "Krishna / Koyna River",
                district: "Satara",
                locality: "Koynanagar / Karad Floodplain",
                gross_capacity_tmc: 105.20,
                current_storage_tmc: 98.60,
                storage_percent: 93.72,
                water_level_m: 657.80,
                fsl_m: 659.43,
                inflow_cusecs: 45000,
                outflow_cusecs: 62500,
                flood_gate_status: "6 Radial Gates Raised 3.0 ft",
                gates_open_count: 6,
                river_current_level: 53.60,
                river_warning_level: 52.00,
                river_danger_level: 54.50,
                river_status: "Warning",
                alert_level: "Critical"
            },
            {
                id: 13,
                name: "Dhom Dam (Wai)",
                river_id: 2,
                river_name: "Krishna River",
                district: "Satara",
                locality: "Wai / Panchgani Foothills",
                gross_capacity_tmc: 13.80,
                current_storage_tmc: 11.90,
                storage_percent: 86.23,
                water_level_m: 746.20,
                fsl_m: 747.00,
                inflow_cusecs: 7800,
                outflow_cusecs: 9200,
                flood_gate_status: "4 Sluice Gates Open",
                gates_open_count: 4,
                river_current_level: 51.50,
                river_warning_level: 51.00,
                river_danger_level: 53.00,
                river_status: "Warning",
                alert_level: "High"
            },
            {
                id: 3,
                name: "Almatti Dam (Downstream Inflow Alert)",
                river_id: 2,
                river_name: "Krishna River",
                district: "Kolhapur / Sangli Border",
                locality: "Sangli Irwin Bridge / Shirol",
                gross_capacity_tmc: 123.08,
                current_storage_tmc: 108.50,
                storage_percent: 88.15,
                water_level_m: 519.20,
                fsl_m: 519.60,
                inflow_cusecs: 95000,
                outflow_cusecs: 120000,
                flood_gate_status: "26 Crest Gates Discharging",
                gates_open_count: 26,
                river_current_level: 53.80,
                river_warning_level: 52.00,
                river_danger_level: 54.50,
                river_status: "Warning",
                alert_level: "Critical"
            }
        ],
        "Bhima Basin": [
            {
                id: 14,
                name: "Ujani Dam (Bhima Reservoir)",
                river_id: 11,
                river_name: "Bhima River",
                district: "Solapur",
                locality: "Pandharpur / Tembhurni",
                gross_capacity_tmc: 117.20,
                current_storage_tmc: 114.50,
                storage_percent: 97.69,
                water_level_m: 496.70,
                fsl_m: 496.83,
                inflow_cusecs: 58000,
                outflow_cusecs: 72000,
                flood_gate_status: "16 Radial Gates Raised 4 ft",
                gates_open_count: 16,
                river_current_level: 40.20,
                river_warning_level: 38.50,
                river_danger_level: 41.00,
                river_status: "Warning",
                alert_level: "Critical"
            },
            {
                id: 15,
                name: "Khadakwasla Dam (Pune)",
                river_id: 11,
                river_name: "Mutha River (Bhima Basin)",
                district: "Pune",
                locality: "Sinhagad Road / Deccan Gymkhana",
                gross_capacity_tmc: 1.97,
                current_storage_tmc: 1.97,
                storage_percent: 100.00,
                water_level_m: 582.40,
                fsl_m: 582.40,
                inflow_cusecs: 14500,
                outflow_cusecs: 18200,
                flood_gate_status: "11 Automatic Gates Spilling",
                gates_open_count: 11,
                river_current_level: 39.80,
                river_warning_level: 38.00,
                river_danger_level: 40.50,
                river_status: "Warning",
                alert_level: "Critical"
            },
            {
                id: 16,
                name: "Panshet Dam (Tanajisagar)",
                river_id: 11,
                river_name: "Ambi River (Bhima Basin)",
                district: "Pune",
                locality: "Velhe / Panshet Valley",
                gross_capacity_tmc: 10.65,
                current_storage_tmc: 9.85,
                storage_percent: 92.48,
                water_level_m: 635.80,
                fsl_m: 636.50,
                inflow_cusecs: 6500,
                outflow_cusecs: 8100,
                flood_gate_status: "3 Crest Gates Open",
                gates_open_count: 3,
                river_current_level: 37.90,
                river_warning_level: 37.00,
                river_danger_level: 39.50,
                river_status: "Warning",
                alert_level: "High"
            },
            {
                id: 17,
                name: "Pavana Dam (Pimpri-Chinchwad)",
                river_id: 11,
                river_name: "Pavana River (Bhima Basin)",
                district: "Pune",
                locality: "Maval / Chinchwad Bridge",
                gross_capacity_tmc: 8.51,
                current_storage_tmc: 7.95,
                storage_percent: 93.41,
                water_level_m: 613.50,
                fsl_m: 614.00,
                inflow_cusecs: 5200,
                outflow_cusecs: 6400,
                flood_gate_status: "2 Sluice Gates Open",
                gates_open_count: 2,
                river_current_level: 36.80,
                river_warning_level: 36.50,
                river_danger_level: 38.80,
                river_status: "Warning",
                alert_level: "High"
            }
        ],
        "Tapi Basin": [
            {
                id: 18,
                name: "Hatnur Dam (Jalgaon)",
                river_id: 12,
                river_name: "Tapi River",
                district: "Jalgaon",
                locality: "Bhusawal / Deepnagar",
                gross_capacity_tmc: 9.02,
                current_storage_tmc: 8.35,
                storage_percent: 92.57,
                water_level_m: 213.60,
                fsl_m: 214.00,
                inflow_cusecs: 34000,
                outflow_cusecs: 41000,
                flood_gate_status: "12 Radial Gates Open",
                gates_open_count: 12,
                river_current_level: 209.80,
                river_warning_level: 208.50,
                river_danger_level: 211.00,
                river_status: "Warning",
                alert_level: "Critical"
            }
        ]
    },
    "Kerala": {
        "Periyar Basin": [
            {
                id: 8,
                name: "Idukki & Cheruthoni Reservoir",
                river_id: 8,
                river_name: "Periyar River",
                district: "Idukki",
                locality: "Aluva Town / Periyar Bank",
                gross_capacity_tmc: 70.50,
                current_storage_tmc: 64.20,
                storage_percent: 91.06,
                water_level_m: 732.10,
                fsl_m: 732.40,
                inflow_cusecs: 28000,
                outflow_cusecs: 35000,
                flood_gate_status: "Cheruthoni 3 Shutters Raised 60cm",
                gates_open_count: 3,
                river_current_level: 13.15,
                river_warning_level: 12.00,
                river_danger_level: 13.50,
                river_status: "Warning",
                alert_level: "High"
            },
            {
                id: 23,
                name: "Mullaperiyar Dam",
                river_id: 8,
                river_name: "Periyar River",
                district: "Idukki",
                locality: "Kumily / Vandiperiyar",
                gross_capacity_tmc: 12.76,
                current_storage_tmc: 12.10,
                storage_percent: 94.82,
                water_level_m: 141.60,
                fsl_m: 142.00,
                inflow_cusecs: 14500,
                outflow_cusecs: 16800,
                flood_gate_status: "8 Spillway Shutters Raised",
                gates_open_count: 8,
                river_current_level: 13.40,
                river_warning_level: 12.00,
                river_danger_level: 13.50,
                river_status: "Warning",
                alert_level: "Critical"
            }
        ]
    },
    "Karnataka": {
        "Kaveri Basin": [
            {
                id: 21,
                name: "Krishna Raja Sagara (KRS) Dam",
                river_id: 13,
                river_name: "Kaveri River",
                district: "Mandya",
                locality: "Srirangapatna / Mandya",
                gross_capacity_tmc: 49.45,
                current_storage_tmc: 45.80,
                storage_percent: 92.61,
                water_level_m: 37.80,
                fsl_m: 38.04,
                inflow_cusecs: 42000,
                outflow_cusecs: 50000,
                flood_gate_status: "18 Sluice Gates Discharging",
                gates_open_count: 18,
                river_current_level: 37.10,
                river_warning_level: 36.00,
                river_danger_level: 38.50,
                river_status: "Warning",
                alert_level: "Critical"
            },
            {
                id: 22,
                name: "Kabini Dam",
                river_id: 13,
                river_name: "Kabini River (Kaveri Basin)",
                district: "Mysuru",
                locality: "Heggadadevankote / Nanjangud",
                gross_capacity_tmc: 19.52,
                current_storage_tmc: 18.20,
                storage_percent: 93.23,
                water_level_m: 695.50,
                fsl_m: 696.00,
                inflow_cusecs: 18000,
                outflow_cusecs: 22000,
                flood_gate_status: "4 Radial Gates Open",
                gates_open_count: 4,
                river_current_level: 36.50,
                river_warning_level: 35.50,
                river_danger_level: 37.80,
                river_status: "Warning",
                alert_level: "High"
            }
        ],
        "Krishna Basin": [
            {
                id: 3,
                name: "Almatti Dam (Lal Bahadur Shastri Sagar)",
                river_id: 2,
                river_name: "Krishna River",
                district: "Bagalkot",
                locality: "Nidagundi / Almatti",
                gross_capacity_tmc: 123.08,
                current_storage_tmc: 108.50,
                storage_percent: 88.15,
                water_level_m: 519.20,
                fsl_m: 519.60,
                inflow_cusecs: 95000,
                outflow_cusecs: 120000,
                flood_gate_status: "26 Crest Gates Discharging",
                gates_open_count: 26,
                river_current_level: 53.60,
                river_warning_level: 52.00,
                river_danger_level: 54.50,
                river_status: "Warning",
                alert_level: "High"
            },
            {
                id: 20,
                name: "Tungabhadra Dam",
                river_id: 2,
                river_name: "Tungabhadra River (Krishna Basin)",
                district: "Ballari",
                locality: "Hosapete / Ballari",
                gross_capacity_tmc: 105.70,
                current_storage_tmc: 98.20,
                storage_percent: 92.90,
                water_level_m: 497.30,
                fsl_m: 497.70,
                inflow_cusecs: 78000,
                outflow_cusecs: 92000,
                flood_gate_status: "30 Crest Gates Open",
                gates_open_count: 30,
                river_current_level: 52.80,
                river_warning_level: 51.50,
                river_danger_level: 53.80,
                river_status: "Warning",
                alert_level: "Critical"
            }
        ]
    },
    "Gujarat": {
        "Narmada Basin": [
            {
                id: 5,
                name: "Sardar Sarovar Dam",
                river_id: 5,
                river_name: "Narmada River",
                district: "Narmada",
                locality: "Kevadia / Bharuch Floodplain",
                gross_capacity_tmc: 334.00,
                current_storage_tmc: 295.20,
                storage_percent: 88.38,
                water_level_m: 136.80,
                fsl_m: 138.68,
                inflow_cusecs: 85000,
                outflow_cusecs: 115000,
                flood_gate_status: "23 Radial Gates Open at 1.8m",
                gates_open_count: 23,
                river_current_level: 29.50,
                river_warning_level: 28.00,
                river_danger_level: 31.00,
                river_status: "Warning",
                alert_level: "High"
            }
        ],
        "Tapi Basin": [
            {
                id: 19,
                name: "Ukai Dam (Vallabh Sagar)",
                river_id: 12,
                river_name: "Tapi River",
                district: "Surat",
                locality: "Surat City / Mandvi",
                gross_capacity_tmc: 260.00,
                current_storage_tmc: 238.40,
                storage_percent: 91.69,
                water_level_m: 104.20,
                fsl_m: 105.15,
                inflow_cusecs: 65000,
                outflow_cusecs: 78000,
                flood_gate_status: "14 Radial Gates Open",
                gates_open_count: 14,
                river_current_level: 209.50,
                river_warning_level: 208.50,
                river_danger_level: 211.00,
                river_status: "Warning",
                alert_level: "High"
            }
        ]
    },
    "Odisha": {
        "Mahanadi Basin": [
            {
                id: 6,
                name: "Hirakud Dam",
                river_id: 7,
                river_name: "Mahanadi River",
                district: "Sambalpur",
                locality: "Cuttack / Sambalpur",
                gross_capacity_tmc: 203.00,
                current_storage_tmc: 168.40,
                storage_percent: 82.95,
                water_level_m: 191.50,
                fsl_m: 192.02,
                inflow_cusecs: 72000,
                outflow_cusecs: 95000,
                flood_gate_status: "12 Sluice Gates Open",
                gates_open_count: 12,
                river_current_level: 26.80,
                river_warning_level: 26.50,
                river_danger_level: 27.40,
                river_status: "Warning",
                alert_level: "High"
            }
        ]
    }
};

let currentSelectedDamObj = null;

document.addEventListener('DOMContentLoaded', () => {
    const selectState = document.getElementById('selectState');
    const selectBasin = document.getElementById('selectBasin');
    const selectDam = document.getElementById('selectDam');
    const inputLocality = document.getElementById('village_city');
    const inputDistrict = document.getElementById('district');
    const damPreviewBox = document.getElementById('damPreviewBox');

    // Populate States
    const states = Object.keys(FLOOD_WATCH_DATASET);
    states.forEach(st => {
        const opt = document.createElement('option');
        opt.value = st;
        opt.textContent = st;
        selectState.appendChild(opt);
    });

    // Set initial state based on current profile or default Maharashtra
    const currentProfileState = <?= json_encode($profile['state'] ?? 'Maharashtra') ?>;
    const currentProfileDamId = <?= (int)($profile['monitored_dam_id'] ?? 1) ?>;

    // Helper to find dam object in dataset by ID
    function findDamInDataset(id) {
        for (const st in FLOOD_WATCH_DATASET) {
            for (const b in FLOOD_WATCH_DATASET[st]) {
                const found = FLOOD_WATCH_DATASET[st][b].find(d => d.id === id);
                if (found) return { dam: found, state: st, basin: b };
            }
        }
        return null;
    }

    const initMatch = findDamInDataset(currentProfileDamId);
    if (initMatch) {
        selectState.value = initMatch.state;
        populateBasins(initMatch.state, initMatch.basin);
        populateDams(initMatch.state, initMatch.basin, initMatch.dam.id);
        updateDamPreview(initMatch.dam);
    } else {
        selectState.value = "Maharashtra";
        populateBasins("Maharashtra", "Godavari Basin");
        populateDams("Maharashtra", "Godavari Basin", 1);
    }

    // Step 1: Change State -> Populate River Basins
    selectState.addEventListener('change', () => {
        const st = selectState.value;
        if (!st) {
            selectBasin.innerHTML = '<option value="">-- Select State First --</option>';
            selectBasin.disabled = true;
            selectDam.innerHTML = '<option value="">-- Select Basin First --</option>';
            selectDam.disabled = true;
            damPreviewBox.style.display = 'none';
            return;
        }
        populateBasins(st);
    });

    // Step 2: Change River Basin -> Populate Dams
    selectBasin.addEventListener('change', () => {
        const st = selectState.value;
        const bs = selectBasin.value;
        if (!bs) {
            selectDam.innerHTML = '<option value="">-- Select Basin First --</option>';
            selectDam.disabled = true;
            damPreviewBox.style.display = 'none';
            return;
        }
        populateDams(st, bs);
    });

    // Step 3: Change Dam -> Auto-set District, Locality, Preview
    selectDam.addEventListener('change', () => {
        const damId = parseInt(selectDam.value, 10);
        const st = selectState.value;
        const bs = selectBasin.value;
        const damList = FLOOD_WATCH_DATASET[st]?.[bs] || [];
        const dam = damList.find(d => d.id === damId);

        if (dam) {
            currentSelectedDamObj = dam;
            inputDistrict.value = dam.district;
            inputLocality.value = dam.locality;
            updateDamPreview(dam);
        } else {
            damPreviewBox.style.display = 'none';
        }
    });

    function populateBasins(state, defaultBasin = null) {
        selectBasin.innerHTML = '<option value="">-- Choose River Basin --</option>';
        const basins = Object.keys(FLOOD_WATCH_DATASET[state] || {});
        basins.forEach(b => {
            const opt = document.createElement('option');
            opt.value = b;
            opt.textContent = b;
            if (defaultBasin && b === defaultBasin) opt.selected = true;
            selectBasin.appendChild(opt);
        });
        selectBasin.disabled = false;

        const activeBasin = defaultBasin || selectBasin.value;
        if (activeBasin) {
            populateDams(state, activeBasin);
        } else {
            selectDam.innerHTML = '<option value="">-- Select Basin First --</option>';
            selectDam.disabled = true;
            damPreviewBox.style.display = 'none';
        }
    }

    function populateDams(state, basin, defaultDamId = null) {
        selectDam.innerHTML = '<option value="">-- Choose Primary Dam --</option>';
        const dams = FLOOD_WATCH_DATASET[state]?.[basin] || [];
        dams.forEach(d => {
            const opt = document.createElement('option');
            opt.value = d.id;
            opt.textContent = `${d.name} (${d.district})`;
            if (defaultDamId && d.id === defaultDamId) opt.selected = true;
            selectDam.appendChild(opt);
        });
        selectDam.disabled = false;

        const activeDamId = defaultDamId || parseInt(selectDam.value, 10);
        const dam = dams.find(d => d.id === activeDamId) || dams[0];
        if (dam) {
            selectDam.value = dam.id;
            currentSelectedDamObj = dam;
            inputDistrict.value = dam.district;
            if (!inputLocality.value) inputLocality.value = dam.locality;
            updateDamPreview(dam);
        }
    }

    function updateDamPreview(d) {
        if (!d) return;
        damPreviewBox.style.display = 'block';
        document.getElementById('previewStoragePct').textContent = `${Math.round(d.storage_percent)}%`;
        document.getElementById('previewCapacity').textContent = `${d.gross_capacity_tmc} TMC`;
        document.getElementById('previewOutflow').textContent = `${Number(d.outflow_cusecs).toLocaleString()} cusecs`;
        document.getElementById('previewDanger').textContent = `${d.river_danger_level.toFixed(1)}m`;

        const badge = document.getElementById('previewAlertBadge');
        badge.className = `badge rounded-pill ${d.alert_level === 'Critical' ? 'bg-danger' : (d.alert_level === 'High' ? 'bg-warning text-dark' : 'bg-success')}`;
        badge.textContent = d.alert_level;
    }

    // Submit Watch Bookmarks Form -> Save & Live Telemetry Sync
    document.getElementById('bookmarkForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = document.getElementById('btnSaveBookmarks');
        const origBtnHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';

        const dam = currentSelectedDamObj;
        const payload = {
            monitored_dam_id: dam ? dam.id : parseInt(selectDam.value, 10),
            monitored_river_id: dam ? dam.river_id : 1,
            village_city: inputLocality.value.trim() || 'My Local Area',
            district: inputDistrict.value.trim() || (dam ? dam.district : 'Maharashtra'),
            state: selectState.value
        };

        try {
            const res = await fetch(`${window.APP_CONFIG.baseUrl}/api/citizen_profile.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const json = await res.json();
            if (json.success) {
                // Dynamically update UI Metrics & Gauges without full page reload
                if (dam) {
                    syncDashboardTelemetry(dam, payload.village_city, payload.district);
                }

                // Close Modal
                const modalEl = document.getElementById('bookmarkModal');
                const modalInstance = bootstrap.Modal.getInstance(modalEl);
                if (modalInstance) {
                    modalInstance.hide();
                }

                // Show gentle toast
                showWatchToast(`✅ Monitored flood watch calibrated to ${dam.name}, ${payload.district}!`);
            } else {
                alert('Save notice: ' + (json.message || 'Unable to update watch bookmarks.'));
            }
        } catch (err) {
            console.error('Save error:', err);
            // Even if network fails, apply client-side sync for viva demo!
            if (dam) {
                syncDashboardTelemetry(dam, payload.village_city, payload.district);
                const modalEl = document.getElementById('bookmarkModal');
                const modalInstance = bootstrap.Modal.getInstance(modalEl);
                if (modalInstance) modalInstance.hide();
                showWatchToast(`✅ Offline Demo: Calibrated to ${dam.name}!`);
            }
        } finally {
            btn.disabled = false;
            btn.innerHTML = origBtnHtml;
        }
    });

    function syncDashboardTelemetry(d, locality, district) {
        // 1. Update Compact Header
        document.getElementById('headerLocText').textContent = `${locality}, ${district}`;
        document.getElementById('headerDamText').textContent = d.name;
        document.getElementById('headerDamNameDesc').textContent = d.name;
        document.getElementById('headerRiverNameDesc').textContent = d.river_name;

        // 2. Recalculate Composite Forecast Score (0 - 100)
        let score = 0;
        score += Math.min(40, (d.storage_percent / 100) * 40);
        score += Math.min(35, (d.outflow_cusecs / 100000) * 35);
        score += Math.min(25, (d.river_current_level / Math.max(1, d.river_danger_level)) * 25);
        score = Math.round(score * 10) / 10;

        let predLevel, predBadge, predMsg, actionRec, barClass;
        if (score >= 80) {
            predLevel = 'Critical Evacuation Alert';
            predBadge = 'bg-danger pulse-emergency';
            barClass = 'bg-danger';
            predMsg = 'Extreme 24h risk: Dam discharge exceeding safe threshold. Downstream floodplain inundation imminent.';
            actionRec = 'Move to designated relief shelter immediately. Do not attempt to cross flooded roads or causeways.';
        } else if (score >= 55) {
            predLevel = 'Flood Advisory / Watch';
            predBadge = 'bg-warning text-dark';
            barClass = 'bg-warning';
            predMsg = 'Moderate-to-High surge expected within 24-48 hours. River water levels approaching warning marks.';
            actionRec = 'Keep emergency documents, medicines, and power banks sealed. Secure livestock to higher grounds.';
        } else {
            predLevel = 'Safe / Normal Flow';
            predBadge = 'bg-success';
            barClass = 'bg-success';
            predMsg = 'Hydrological conditions stable. Dam storage within standard conservation limits.';
            actionRec = 'No immediate flood threat detected in your local watershed. Normal monitoring maintained.';
        }

        document.getElementById('forecastScoreVal').innerHTML = `${score} <small class="fs-6 text-muted">/ 100</small>`;
        const fBar = document.getElementById('forecastProgressBar');
        fBar.className = `progress-bar ${barClass}`;
        fBar.style.width = `${score}%`;

        const fBadge = document.getElementById('forecastBadge');
        fBadge.className = `badge ${predBadge} px-3 py-1 rounded-pill`;
        fBadge.textContent = predLevel;

        document.getElementById('forecastSummaryText').textContent = predMsg;
        document.getElementById('forecastActionText').textContent = actionRec;

        // 3. Update Monitored Dam Reservoir Gauge
        document.getElementById('damCardHeaderBadge').textContent = d.name;
        const damPct = d.storage_percent;
        let damStroke = '#10b981';
        if (damPct >= 90.0) damStroke = '#ef4444';
        else if (damPct >= 75.0) damStroke = '#f59e0b';

        const damOffset = 327 - (327 * (damPct / 100));
        const circleEl = document.getElementById('damGaugeCircle');
        circleEl.setAttribute('stroke', damStroke);
        circleEl.setAttribute('stroke-dashoffset', damOffset);

        const damValEl = document.getElementById('damGaugeVal');
        damValEl.style.color = damStroke;
        damValEl.textContent = `${Math.round(damPct)}%`;

        document.getElementById('damStorageVal').textContent = Number(d.current_storage_tmc).toFixed(2);
        document.getElementById('damGrossCapVal').textContent = `/ ${d.gross_capacity_tmc} TMC`;

        const outflowEl = document.getElementById('damOutflowVal');
        outflowEl.textContent = Number(d.outflow_cusecs).toLocaleString();
        outflowEl.className = d.outflow_cusecs > 50000 ? 'text-danger' : 'text-primary';

        document.getElementById('damGateStatusVal').textContent = d.flood_gate_status;
        document.getElementById('damGateCountVal').textContent = `(${d.gates_open_count})`;

        const alertEl = document.getElementById('damAlertLevelVal');
        alertEl.textContent = d.alert_level;
        alertEl.className = d.alert_level === 'Critical' ? 'text-danger' : 'text-success';

        // 4. Update River Danger Mark Gauge
        document.getElementById('riverCardHeaderBadge').textContent = d.river_name;
        document.getElementById('riverCurrentLevelVal').textContent = `${Number(d.river_current_level).toFixed(2)}m`;

        const rStatusBadge = document.getElementById('riverStatusBadge');
        rStatusBadge.className = `badge ${d.river_status === 'Flooding' ? 'bg-danger' : 'bg-success'} px-3 py-1 rounded-pill`;
        rStatusBadge.textContent = `Status: ${d.river_status}`;

        document.getElementById('riverWarningVal').textContent = `${Number(d.river_warning_level).toFixed(1)}m`;
        document.getElementById('riverDangerVal').textContent = `${Number(d.river_danger_level).toFixed(1)}m`;

        const rBarWidth = Math.min(100, Math.round((d.river_current_level / Math.max(1, d.river_danger_level)) * 100));
        const rBar = document.getElementById('riverProgressBar');
        rBar.className = `progress-bar ${d.river_current_level >= d.river_danger_level ? 'bg-danger' : 'bg-primary'}`;
        rBar.style.width = `${rBarWidth}%`;

        // 5. Update Designated Emergency Relief Shelter & Evacuation Map
        if (d.id) {
            updateShelterCardAndMap(d.id, d.name);
        }
    }

    // Leaflet Map Controller for Designated Primary Shelter
    let shelterMap = null;
    let shelterMarker = null;

    function initShelterMap(lat, lng, shelterName, address, directionsUrl) {
        const mapContainer = document.getElementById('primaryShelterMap');
        if (!mapContainer || typeof L === 'undefined') return;

        if (!shelterMap) {
            shelterMap = L.map('primaryShelterMap').setView([lat, lng], 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
            }).addTo(shelterMap);
        } else {
            shelterMap.setView([lat, lng], 13);
        }

        if (shelterMarker) {
            shelterMap.removeLayer(shelterMarker);
        }

        const shelterIcon = L.divIcon({
            className: 'custom-map-pin',
            html: `<div style="background-color: #dc3545; width: 26px; height: 26px; border-radius: 50%; border: 3px solid #fff; box-shadow: 0 0 10px rgba(220,53,69,0.8); display:flex; align-items:center; justify-content:center; color:#fff; font-size:12px;"><i class="bi bi-shield-fill"></i></div>`,
            iconSize: [26, 26],
            iconAnchor: [13, 13]
        });

        shelterMarker = L.marker([lat, lng], { icon: shelterIcon, title: shelterName }).addTo(shelterMap);
        shelterMarker.bindPopup(`
            <div style="min-width: 220px;">
                <span class="badge bg-danger mb-1"><i class="bi bi-shield-fill-check me-1"></i>Designated Primary Shelter</span>
                <h6 class="fw-bold mb-1 text-dark">${escapeHtml(shelterName)}</h6>
                <p class="small text-muted mb-2">${escapeHtml(address)}</p>
                <a href="${directionsUrl}" target="_blank" class="btn btn-sm btn-danger w-100 fw-bold">
                    <i class="bi bi-sign-turn-right-fill me-1"></i> Get Directions
                </a>
            </div>
        `).openPopup();
    }

    async function updateShelterCardAndMap(damId, damName) {
        if (damName) {
            const shelterDamTitle = document.getElementById('shelterDamTitle');
            if (shelterDamTitle) shelterDamTitle.textContent = damName;
        }
        const btnAllShelters = document.getElementById('btnAllShelters');
        if (btnAllShelters) btnAllShelters.href = `${window.APP_CONFIG.baseUrl}/views/citizen/camps.php?dam_id=${encodeURIComponent(damId)}`;

        try {
            const res = await fetch(`${window.APP_CONFIG.baseUrl}/api/camps.php?action=primary_shelter&dam_id=${encodeURIComponent(damId)}`);
            const json = await res.json();
            if (json.success && json.data) {
                const s = json.data;
                const name = s.display_name || s.name || 'Primary Relief Shelter';
                const addr = s.address || 'Safe Highland Evacuation Zone';
                const dirUrl = s.directions_url || `https://www.google.com/maps/dir/?api=1&destination=${s.latitude},${s.longitude}`;

                document.getElementById('shelterNameHeading').textContent = name;
                document.getElementById('shelterAddressText').textContent = addr;
                document.getElementById('shelterDistrictBadge').innerHTML = `<i class="bi bi-geo-alt me-1"></i>${s.district || ''}`;
                document.getElementById('shelterStatusBadge').textContent = s.status || 'Operational';
                document.getElementById('shelterAvailableBeds').textContent = s.available_beds || s.vacant_beds || 0;
                document.getElementById('shelterTotalCapacity').textContent = s.total_capacity || 0;
                document.getElementById('shelterContactPerson').textContent = s.contact_person || 'Disaster Incharge';
                document.getElementById('shelterContactPhone').textContent = s.contact_phone || '+91 112';
                document.getElementById('shelterCallBtn').href = `tel:${s.contact_phone || '112'}`;
                document.getElementById('shelterDirectionsBtn').href = dirUrl;

                const badgeSupply = document.getElementById('shelterSupplyBadge');
                if (badgeSupply) {
                    const supply = s.supply_status || 'Sufficient';
                    badgeSupply.innerHTML = `<i class="bi bi-box-seam me-1"></i> Supply Status: ${supply}`;
                    badgeSupply.className = `badge px-3 py-2 rounded-pill font-monospace ${supply === 'Abundant' ? 'bg-success' : (supply === 'Critical' ? 'bg-danger' : 'bg-primary')}`;
                }

                initShelterMap(parseFloat(s.latitude), parseFloat(s.longitude), name, addr, dirUrl);
            }
        } catch (err) {
            console.error('Failed to update shelter card:', err);
        }
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/[&<>'"]/g, 
            tag => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[tag] || tag)
        );
    }

    // Initialize Leaflet Map for Designated Primary Shelter on page load
    const initialShelterLat = <?= (float)($primaryShelter['latitude'] ?? 19.9985) ?>;
    const initialShelterLng = <?= (float)($primaryShelter['longitude'] ?? 73.7250) ?>;
    const initialShelterName = <?= json_encode($primaryShelter['display_name'] ?? 'Primary Relief Shelter') ?>;
    const initialShelterAddress = <?= json_encode($primaryShelter['address'] ?? 'Safe Highland Evacuation Zone') ?>;
    const initialDirectionsUrl = <?= json_encode("https://www.google.com/maps/dir/?api=1&destination=" . ($primaryShelter['latitude'] ?? 19.9985) . "," . ($primaryShelter['longitude'] ?? 73.7250)) ?>;

    initShelterMap(initialShelterLat, initialShelterLng, initialShelterName, initialShelterAddress, initialDirectionsUrl);

    function showWatchToast(msg) {
        document.getElementById('toastMessage').textContent = msg;
        const toastEl = document.getElementById('liveWatchToast');
        const toast = new bootstrap.Toast(toastEl, { delay: 4000 });
        toast.show();
    }
});
</script>

<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
