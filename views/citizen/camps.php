<?php
/**
 * Relief Camp Locator & Geospatial Proximity Page
 * Satisfies PRD Section 6 (Camp Locator Map) and FR-09 to FR-12
 */

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once dirname(__DIR__, 2) . '/config/maps_config.php';
require_once dirname(__DIR__, 2) . '/core/Database.php';
require_once dirname(__DIR__, 2) . '/core/Helper.php';

$db = Database::getInstance();
$zones = [];
if ($db->isConnected()) {
    $zones = $db->fetchAll("SELECT * FROM `zones` WHERE `status` = 'active' ORDER BY `name` ASC");
}

$preselectedZoneId = (int)($_GET['zone_id'] ?? 0);

$pageTitle = "Relief Camp Locator Map";
require_once dirname(__DIR__) . '/layouts/header.php';
?>

<div class="container py-4">
    <!-- Header Banner -->
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="bi bi-geo-alt-fill text-danger me-2"></i>Relief Camp &amp; Shelter Locator
            </h2>
            <p class="text-muted small mb-0">
                Discover safe evacuation centers, check live bed capacity, and generate immediate navigation directions.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button id="btnResetView" class="btn btn-outline-secondary btn-sm shadow-sm" title="View all relief camps across India">
                <i class="bi bi-globe-central-south me-1"></i> Pan-India View
            </button>
            <button id="btnLocateMe" class="btn btn-primary btn-sm shadow-sm">
                <i class="bi bi-crosshair me-1"></i> Use My Current Location
            </button>
        </div>
    </div>

    <!-- Filter Controls Card -->
    <div class="card shadow-sm border-0 mb-4 rounded-3">
        <div class="card-body p-3">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="filterZone" class="form-label small fw-semibold text-muted mb-1">Filter by Zone:</label>
                    <select id="filterZone" class="form-select form-select-sm">
                        <option value="">-- All Monitoring Zones (Pan-India) --</option>
                        <?php foreach ($zones as $z): ?>
                            <option value="<?= $z['id'] ?>" <?= $z['id'] == $preselectedZoneId ? 'selected' : '' ?>>
                                <?= Helper::e($z['name']) ?><?= !empty($z['state']) ? ' (' . Helper::e($z['state']) . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="filterRadius" class="form-label small fw-semibold text-muted mb-1">Search Radius:</label>
                    <select id="filterRadius" class="form-select form-select-sm">
                        <option value="all" selected>All India (Pan-India Shelters)</option>
                        <option value="25">25 Kilometers</option>
                        <option value="50">50 Kilometers</option>
                        <option value="100">100 Kilometers</option>
                        <option value="250">250 Kilometers</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="filterStatus" class="form-label small fw-semibold text-muted mb-1">Operational State:</label>
                    <select id="filterStatus" class="form-select form-select-sm">
                        <option value="Operational" selected>Operational Shelters</option>
                        <option value="Available">Has Available Beds</option>
                        <option value="All">All Camps (Including Full)</option>
                    </select>
                </div>
                <div class="col-md-3 d-grid">
                    <button id="btnApplyFilter" class="btn btn-primary btn-sm">
                        <i class="bi bi-funnel-fill me-1"></i> Apply Filter
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Map & List Grid -->
    <div class="row g-4">
        <!-- Interactive Map Container -->
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 rounded-3 overflow-hidden">
                <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center border-0">
                    <span class="small fw-semibold text-dark">
                        <i class="bi bi-map me-1 text-primary"></i> Interactive Shelter Navigation Map
                    </span>
                    <div class="small text-muted d-flex align-items-center gap-3">
                        <span><span class="badge bg-success">&bull;</span> Available</span>
                        <span><span class="badge bg-warning">&bull;</span> &gt;85% Full</span>
                        <span><span class="badge bg-danger">&bull;</span> Full</span>
                        <span><span class="badge bg-primary">&bull;</span> Your Location</span>
                    </div>
                </div>
                <div id="campMap" style="height: 580px;"></div>
                <div class="card-footer bg-light py-2 small text-muted text-center">
                    Click any camp marker for contact details, capacity breakdown, and driving/walking directions.
                </div>
            </div>
        </div>

        <!-- Scrollable Nearby Camps List -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 rounded-3 h-100">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold text-dark mb-0">Nearby Relief Centers</h6>
                    <span id="campCountBadge" class="badge bg-primary">0 found</span>
                </div>
                <div class="card-body p-2 overflow-auto" id="campsListContainer" style="max-height: 560px;">
                    <div class="text-center py-5 text-muted">
                        <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
                        <div>Loading shelters in range...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Floating Non-Blocking Toast Notification for Geolocation -->
<div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1090;">
    <div id="geoNotificationToast" class="toast align-items-center border-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body d-flex align-items-center gap-2">
                <i id="geoToastIcon" class="bi bi-info-circle-fill fs-5 text-primary"></i>
                <div>
                    <strong id="geoToastTitle" class="d-block text-dark">Location Update</strong>
                    <span id="geoToastMessage" class="text-muted small">Using default regional location.</span>
                </div>
            </div>
            <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<!-- Map Adapter Script -->
<script src="<?= BASE_URL ?>/assets/js/map_adapter.js"></script>
<!-- Camp Locator Frontend Controller -->
<script src="<?= BASE_URL ?>/assets/js/camp_locator.js"></script>

<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
