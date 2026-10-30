<?php
// Turn off display of notices that break JSON responses
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

header('Content-Type: application/json; charset=utf-8');
ob_start();
ob_start();
/**
 * Public Flood Risk Dashboard
 * Satisfies PRD Section 6 (Risk Dashboard) and FR-01 through FR-08
 */

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/core/Database.php';
require_once dirname(__DIR__, 2) . '/core/Helper.php';
require_once dirname(__DIR__, 2) . '/services/RiskRuleEngine.php';

$db = Database::getInstance();
$zones = [];
$stats = [
    'total_zones' => 0,
    'critical_zones' => 0,
    'active_camps' => 0,
    'available_beds' => 0
];

if ($db->isConnected()) {
    $zones = $db->fetchAll("SELECT * FROM zones WHERE status = 'active' ORDER BY name ASC");

    // Precalculate quick statistics
    $stats['total_zones'] = count($zones);
    $stats['critical_zones'] = (int)$db->fetchValue("
        SELECT COUNT(DISTINCT z.id) 
        FROM zones z
        JOIN risk_levels rl ON rl.id = (
            SELECT id FROM risk_levels WHERE zone_id = z.id ORDER BY observed_at DESC, id DESC LIMIT 1
        )
        WHERE z.status = 'active' AND rl.category = 'Critical'
    ");
    $stats['active_camps'] = (int)$db->fetchValue("SELECT COUNT(*) FROM camps WHERE active = true AND status != 'Closed'");
    $stats['available_beds'] = (int)$db->fetchValue("SELECT SUM(total_capacity - current_occupancy) FROM camps WHERE active = true AND status = 'Operational'");
}

$selectedZoneId = (int)($_GET['zone_id'] ?? ($zones[0]['id'] ?? 1));

$pageTitle = "Flood Risk Dashboard";
require_once dirname(__DIR__) . '/layouts/header.php';
?>

<!-- Hero & Emergency Helpline Header -->
<div class="bg-primary bg-gradient text-white py-4 mb-4 shadow-sm">
    <div class="container">
        <div class="row align-items-center g-3">
            <div class="col-lg-8">
                <span class="badge bg-light text-primary fw-semibold px-2 py-1 mb-2">
                    <i class="bi bi-broadcast me-1"></i> REAL-TIME DECISION SUPPORT
                </span>
                <h1 class="h2 fw-bold mb-1">Flood Risk Assessment &amp; Early Warning</h1>
                <p class="mb-0 text-white-50">
                    Rule-based hydrological decision support model converting rainfall, river levels, and watershed observations into actionable citizen safety levels.
                </p>
            </div>
            <div class="col-lg-4 text-lg-end">
                <div class="d-inline-flex flex-column align-items-lg-end">
                    <span class="small text-white-50">State Emergency Helpline:</span>
                    <a href="tel:112" class="btn btn-warning btn-lg fw-bold shadow-sm px-4">
                        <i class="bi bi-telephone-outbound-fill me-2"></i> Call 112 (Rescue)
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container pb-5">
    <!-- Key Statistics Row -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card stat-card p-3 border-0 bg-white">
                <div class="d-flex align-items-center">
                    <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-3 me-3">
                        <i class="bi bi-map-fill fs-4"></i>
                    </div>
                    <div>
                        <div class="small text-muted">Monitored Zones</div>
                        <h4 class="fw-bold mb-0"><?= $stats['total_zones'] ?></h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card stat-card p-3 border-0 bg-white">
                <div class="d-flex align-items-center">
                    <div class="p-3 bg-danger bg-opacity-10 text-danger rounded-3 me-3">
                        <i class="bi bi-exclamation-octagon-fill fs-4"></i>
                    </div>
                    <div>
                        <div class="small text-muted">Critical Inundations</div>
                        <h4 class="fw-bold mb-0 text-danger"><?= $stats['critical_zones'] ?></h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card stat-card p-3 border-0 bg-white">
                <div class="d-flex align-items-center">
                    <div class="p-3 bg-success bg-opacity-10 text-success rounded-3 me-3">
                        <i class="bi bi-hospital-fill fs-4"></i>
                    </div>
                    <div>
                        <div class="small text-muted">Active Relief Camps</div>
                        <h4 class="fw-bold mb-0 text-success"><?= $stats['active_camps'] ?></h4>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card stat-card p-3 border-0 bg-white">
                <div class="d-flex align-items-center">
                    <div class="p-3 bg-info bg-opacity-10 text-info rounded-3 me-3">
                        <i class="bi bi-people-fill fs-4"></i>
                    </div>
                    <div>
                        <div class="small text-muted">Available Shelter Beds</div>
                        <h4 class="fw-bold mb-0 text-info"><?= max(0, $stats['available_beds']) ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Area Selection Card -->
    <div class="card shadow-sm border-0 mb-4 rounded-3">
        <div class="card-body p-4">
            <div class="row align-items-center g-3">
                <div class="col-md-5">
                    <label for="zoneSelector" class="form-label fw-bold text-dark mb-1">
                        <i class="bi bi-geo-alt-fill text-danger me-1"></i> Select Disaster Monitoring Zone / River Basin:
                    </label>
                    <select class="form-select form-select-lg" id="zoneSelector">
                        <?php foreach ($zones as $z): ?>
                            <option value="<?= $z['id'] ?>" <?= $z['id'] == $selectedZoneId ? 'selected' : '' ?>>
                                <?= Helper::e($z['name']) ?> (<?= Helper::e($z['district']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-7 text-md-end pt-md-4">
                    <a href="<?= BASE_URL ?>/views/citizen/camps.php?zone_id=<?= $selectedZoneId ?>" id="btnViewCamps" class="btn btn-outline-primary me-2">
                        <i class="bi bi-compass me-1"></i> View Nearby Camps
                    </a>
                    <a href="<?= BASE_URL ?>/views/citizen/request_help.php?zone_id=<?= $selectedZoneId ?>" id="btnRequestHelp" class="btn btn-danger">
                        <i class="bi bi-life-preserver me-1"></i> Emergency Assistance Request
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Dynamic Risk Details Container (Populated via JS & Pre-rendered) -->
    <div id="riskLoadingSpinner" class="text-center py-5 d-none">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Calculating risk indicators...</span>
        </div>
        <p class="text-muted mt-2">Retrieving latest observations &amp; applying rule engine...</p>
    </div>

    <div id="riskDisplayContainer">
        <!-- Will be refreshed dynamically by risk_dashboard.js -->
        <div class="row g-4">
            <!-- Left: Main Risk Gauge & Score Card -->
            <div class="col-lg-5">
                <div class="card shadow-sm border-0 h-100 rounded-3" id="mainScoreCard">
                    <div class="card-header bg-white py-3 border-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="fw-bold mb-0 text-dark" id="zoneNameHeader">Loading Zone...</h5>
                            <span id="dataFreshnessBadge" class="badge bg-secondary">Checking...</span>
                        </div>
                        <small class="text-muted" id="zoneDistrictSubtitle"></small>
                    </div>
                    <div class="card-body text-center p-4">
                        <div class="py-2">
                            <span class="text-muted small text-uppercase fw-bold letter-spacing">Calculated Flood Threat Level</span>
                            <div class="display-3 fw-bold my-2" id="scoreNumber">--</div>
                            <div class="mb-3">
                                <span class="badge fs-5 px-4 py-2" id="categoryBadge">Calculating...</span>
                            </div>
                        </div>

                        <!-- Visual Risk Meter Bar -->
                        <div class="my-4 text-start">
                            <div class="d-flex justify-content-between small text-muted mb-1 font-monospace">
                                <span>0 (Low)</span>
                                <span>25 (Mod)</span>
                                <span>50 (High)</span>
                                <span>75 (Critical)</span>
                                <span>100</span>
                            </div>
                            <div class="progress risk-progress">
                                <div id="riskProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%;"></div>
                            </div>
                        </div>

                        <div class="p-3 bg-light rounded-3 text-start small border">
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">Observation Source:</span>
                                <strong id="obsSource">--</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted">Recorded Timestamp:</span>
                                <strong id="obsTimestamp">--</strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Data Freshness:</span>
                                <strong id="obsFreshness">--</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right: Contributing Hydrological Factors & Citizen Safety Guidance -->
            <div class="col-lg-7">
                <!-- Zone Emergency Alert Ticker (if active) -->
                <div id="zoneAlertContainer" class="mb-4 d-none">
                    <div class="alert alert-danger border-danger shadow-sm d-flex align-items-start gap-3 rounded-3" role="alert">
                        <i class="bi bi-exclamation-triangle-fill fs-3 text-danger flex-shrink-0"></i>
                        <div>
                            <h6 class="alert-heading fw-bold mb-1" id="alertTitle">--</h6>
                            <p class="mb-1 small" id="alertMessage">--</p>
                            <span class="badge bg-danger" id="alertSeverity">--</span>
                        </div>
                    </div>
                </div>

                <!-- Hydrological Indicators -->
                <div class="row g-3 mb-4">
                    <div class="col-sm-6">
                        <div class="card shadow-sm border-0 p-3 rounded-3 bg-white h-100">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div class="text-muted small fw-semibold">24-Hour Cumulative Rainfall</div>
                                <i class="bi bi-cloud-rain-heavy-fill text-primary fs-4"></i>
                            </div>
                            <h3 class="fw-bold text-dark mb-1"><span id="rainfallVal">--</span> <small class="fs-6 text-muted">mm</small></h3>
                            <div class="progress mb-2" style="height: 6px;">
                                <div id="rainfallNormBar" class="progress-bar bg-primary" role="progressbar" style="width: 0%"></div>
                            </div>
                            <small class="text-muted">Normalized factor: <span id="rainfallNormVal">--</span>/100 (Weight: <span id="rainfallWeight">--</span>)</small>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="card shadow-sm border-0 p-3 rounded-3 bg-white h-100">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div class="text-muted small fw-semibold">River Water Surface Level</div>
                                <i class="bi bi-water text-info fs-4"></i>
                            </div>
                            <h3 class="fw-bold text-dark mb-1"><span id="riverVal">--</span> <small class="fs-6 text-muted">meters</small></h3>
                            <div class="progress mb-2" style="height: 6px;">
                                <div id="riverNormBar" class="progress-bar bg-info" role="progressbar" style="width: 0%"></div>
                            </div>
                            <small class="text-muted">Normalized factor: <span id="riverNormVal">--</span>/100 (Weight: <span id="riverWeight">--</span>)</small>
                        </div>
                    </div>
                </div>

                <!-- Citizen Action Directives Card -->
                <div class="card shadow-sm border-0 rounded-3">
                    <div class="card-header bg-white py-3 border-0">
                        <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-shield-check text-success me-2"></i>Actionable Safety Guidance</h5>
                    </div>
                    <div class="card-body pt-0">
                        <p class="text-muted fw-semibold mb-3" id="guidanceSummary">Loading recommendations...</p>
                        <h6 class="fw-bold text-dark small text-uppercase">Recommended Citizen Next Steps:</h6>
                        <ul class="list-group list-group-flush mb-3" id="guidanceActions">
                            <!-- Injected by JS -->
                        </ul>

                        <div class="p-3 bg-light rounded-3 border">
                            <h6 class="fw-bold text-dark small text-uppercase mb-1"><i class="bi bi-megaphone-fill me-1 text-primary"></i> Disaster Authority Directives:</h6>
                            <p class="mb-0 text-muted small" id="guidanceCoordinator">--</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Historical Trend Row -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card shadow-sm border-0 rounded-3">
                    <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold mb-0 text-dark"><i class="bi bi-clock-history me-2"></i>Recent Hydrological Observation History</h6>
                        <span class="small text-muted">Past 5 recorded telemetry checkpoints</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small">
                                <tr>
                                    <th>Observed At</th>
                                    <th>Rainfall (mm)</th>
                                    <th>River Level (m)</th>
                                    <th>Computed Score</th>
                                    <th>Risk Category</th>
                                    <th>Telemetry Source</th>
                                </tr>
                            </thead>
                            <tbody id="historyTableBody">
                                <!-- Populated by JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Dedicated Client-side Risk Dashboard Logic -->
<script src="<?= BASE_URL ?>/assets/js/risk_dashboard.js"></script>

<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>

ob_clean();
echo json_encode($response);
exit;