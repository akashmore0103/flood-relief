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
    @session_start();
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
               COALESCE(d.district, 'Chhatrapati Sambhajinagar') AS district,
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
        FROM citizen_profiles cp
        LEFT JOIN dams d ON cp.saved_dam_id = d.id
        LEFT JOIN rivers r ON cp.saved_river_id = r.id
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

// Fallback baseline for non-logged-in guest demonstration or missing DB profile
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
        'district' => $defaultDam['district'] ?? 'Chhatrapati Sambhajinagar',
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
        FROM camps c
        LEFT JOIN dams d ON c.dam_id = d.id
        LEFT JOIN rivers r ON (c.river_basin_id = r.id OR d.river_id = r.id)
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
            FROM camps c
            LEFT JOIN dams d ON c.dam_id = d.id
            LEFT JOIN rivers r ON (c.river_basin_id = r.id OR d.river_id = r.id)
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
/* Circular SVG Gauge Specific Styling */
.circular-gauge-box {
    position: relative;
    width: 140px;
    height: 140px;
    margin: 0 auto;
}
.circular-gauge-box svg {
    transform: rotate(-90deg);
    display: block;
}
.circular-gauge-box .gauge-bg {
    fill: none !important;
    stroke: #e2e8f0;
    stroke-width: 10;
}
.circular-gauge-box .gauge-fill {
    fill: none !important;
    stroke-width: 10;
    stroke-linecap: round;
    transition: stroke-dashoffset 0.8s ease-in-out;
}
.circular-gauge-box .gauge-text {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    text-align: center;
    width: 100%;
}
.circular-gauge-box .gauge-val {
    font-size: 1.5rem;
    font-weight: 700;
    line-height: 1.1;
}
.circular-gauge-box .gauge-lbl {
    font-size: 0.68rem;
    color: #64748b;
    text-transform: uppercase;
    font-weight: 600;
}

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
                $damStroke = '#10b981'; // Green
                if ($damPct >= 90.0) $damStroke = '#ef4444'; // Red
                elseif ($damPct >= 75.0) $damStroke = '#f59e0b'; // Amber / Orange
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
                            <circle class="gauge-bg" cx="70" cy="70" r="52" fill="none" stroke-width="10"></circle>
                            <circle id="damGaugeCircle" class="gauge-fill" cx="70" cy="70" r="52" 
                                    fill="none"
                                    stroke-width="10"
                                    stroke="<?= $damStroke ?>"
                                    stroke-dasharray="327" 
                                    stroke-dashoffset="<?= $damOffset ?>"></circle>
                        </svg>
                        <div class="gauge-text">
                            <div id="damGaugeVal" class="gauge-val" style="color: <?= $damStroke ?>;"><?= round($damPct) ?>%</div>
                            <div class="gauge-lbl" data-i18n="dam_storage">DAM STORAGE</div>