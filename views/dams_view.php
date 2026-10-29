<?php
/**
 * Major Indian Rivers & Dam Telemetry Dashboard
 * CWC / data.gov.in integration with offline database cache fallback
 * Flood Risk Prediction & Relief Camp Locator
 */

require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/core/Database.php';
require_once dirname(__DIR__) . '/core/Auth.php';
require_once dirname(__DIR__) . '/core/Helper.php';
require_once dirname(__DIR__) . '/core/I18n.php';
require_once dirname(__DIR__) . '/services/DamDataService.php';

$selectedRiver = !empty($_GET['river_id']) ? (int)$_GET['river_id'] : null;
$rivers = DamDataService::getAllRivers();
$dams = DamDataService::getAllDams($selectedRiver);

$pageTitle = "Major Indian Rivers & Dam Telemetry";
require_once __DIR__ . '/layouts/header.php';
?>

<style>
/* Circular SVG Gauge Styling */
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
</style>

<div class="container-fluid px-lg-4 py-4">
    <!-- Header Banner -->
    <div class="glass-panel p-4 mb-4">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <span class="badge bg-primary px-3 py-1 mb-2 font-monospace" data-i18n="nav_dams">HYDROLOGICAL TELEMETRY</span>
                <h2 class="fw-bold text-dark mb-1">Major Indian Rivers &amp; Dam Reservoirs</h2>
                <p class="text-muted mb-0 small">
                    Live telemetry tracking Central Water Commission (CWC) reservoirs, river danger benchmarks, gate discharges, and downstream floodplain safety.
                </p>
            </div>
            <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2">
                    <i class="bi bi-shield-check me-1"></i> CWC Offline Guaranteed Cache
                </span>
            </div>
        </div>
    </div>

    <!-- River Basin Quick Filter Chips -->
    <div class="d-flex align-items-center gap-2 overflow-x-auto pb-3 mb-4">
        <span class="text-muted small fw-bold text-uppercase flex-shrink-0">River Basin:</span>
        <a href="<?= BASE_URL ?>/views/dams_view.php" 
           class="btn btn-sm <?= empty($selectedRiver) ? 'btn-primary' : 'btn-outline-secondary' ?> rounded-pill px-3">
            All Major Basins (<?= count($dams) ?> Dams)
        </a>
        <?php foreach ($rivers as $r): ?>
            <a href="<?= BASE_URL ?>/views/dams_view.php?river_id=<?= $r['id'] ?>" 
               class="btn btn-sm <?= $selectedRiver === (int)$r['id'] ? 'btn-primary' : 'btn-outline-secondary' ?> rounded-pill px-3 flex-shrink-0">
                <?= Helper::e($r['name']) ?> (<?= (int)$r['dam_count'] ?>)
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Dams Grid -->
    <div class="row g-4">
        <?php foreach ($dams as $dam): ?>
            <?php 
                $pct = (float)$dam['storage_percent'];
                $strokeColor = '#10b981'; // Green
                $badgeClass = 'bg-success';
                if ($pct >= 90.0) {
                    $strokeColor = '#ef4444'; // Red
                    $badgeClass = 'bg-danger pulse-emergency';
                } elseif ($pct >= 75.0) {
                    $strokeColor = '#f59e0b'; // Amber
                    $badgeClass = 'bg-warning text-dark';
                }
                // SVG Circle math: circumference = 2 * PI * r = 2 * 3.14159 * 52 ≈ 326.7
                $dashoffset = 327 - (327 * ($pct / 100));
            ?>
            <div class="col-xl-3 col-lg-4 col-md-6">
                <div class="card glass-card h-100 border-0 shadow-sm overflow-hidden">
                    <div class="card-header bg-transparent border-0 pt-3 pb-0 d-flex justify-content-between align-items-start">
                        <div>
                            <span class="badge bg-secondary-subtle text-secondary small mb-1"><?= Helper::e($dam['state']) ?></span>
                            <h5 class="fw-bold mb-0 text-dark"><?= Helper::e($dam['name']) ?></h5>
                            <small class="text-muted"><i class="bi bi-water text-primary me-1"></i><?= Helper::e($dam['river_name']) ?> Basin</small>
                        </div>
                        <span class="badge <?= $badgeClass ?> px-2 py-1"><?= Helper::e($dam['alert_level']) ?></span>
                    </div>

                    <div class="card-body py-3 text-center">
                        <!-- Animated Circular Gauge -->
                        <div class="circular-gauge-box my-2">
                            <svg width="140" height="140" viewBox="0 0 140 140">
                                <circle class="gauge-bg" cx="70" cy="70" r="52" fill="none" stroke-width="10"></circle>
                                <circle class="gauge-fill" cx="70" cy="70" r="52" 
                                        fill="none"
                                        stroke-width="10"
                                        stroke="<?= $strokeColor ?>"
                                        stroke-dasharray="327" 
                                        stroke-dashoffset="<?= $dashoffset ?>"></circle>
                            </svg>
                            <div class="gauge-text">
                                <div class="gauge-val" style="color: <?= $strokeColor ?>;"><?= round($pct) ?>%</div>
                                <div class="gauge-lbl" data-i18n="dam_storage">Capacity</div>
                            </div>
                        </div>

                        <!-- Telemetry Metrics Grid -->
                        <div class="row g-2 text-start mt-2">
                            <div class="col-6">
                                <div class="p-2 bg-light rounded-3">
                                    <small class="text-muted d-block" style="font-size: 0.72rem;">Live Storage</small>
                                    <strong class="text-dark"><?= number_format($dam['current_storage_tmc'], 2) ?></strong>
                                    <span class="text-muted" style="font-size: 0.75rem;">/ <?= $dam['gross_capacity_tmc'] ?> TMC</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2 bg-light rounded-3">
                                    <small class="text-muted d-block" style="font-size: 0.72rem;">Discharge (Outflow)</small>
                                    <strong class="<?= (float)$dam['outflow_cusecs'] > 50000 ? 'text-danger' : 'text-primary' ?>">
                                        <?= number_format($dam['outflow_cusecs']) ?>
                                    </strong>
                                    <span class="text-muted" style="font-size: 0.75rem;">cusecs</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2 bg-light rounded-3">
                                    <small class="text-muted d-block" style="font-size: 0.72rem;">Inflow Rate</small>
                                    <strong class="text-dark"><?= number_format($dam['inflow_cusecs']) ?></strong>
                                    <span class="text-muted" style="font-size: 0.75rem;">cusecs</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2 bg-light rounded-3">
                                    <small class="text-muted d-block" style="font-size: 0.72rem;">Flood Gates</small>
                                    <strong class="<?= $dam['flood_gate_status'] === 'Fully Open' ? 'text-danger' : 'text-dark' ?>">
                                        <?= Helper::e($dam['flood_gate_status']) ?>
                                    </strong>
                                    <?php if (!empty($dam['gates_open_count'])): ?>
                                        <span class="text-muted" style="font-size: 0.75rem;">(<?= (int)$dam['gates_open_count'] ?>)</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- River Benchmarks Progress -->
                        <div class="mt-3 p-2 border rounded-3 text-start bg-white">
                            <div class="d-flex justify-content-between small mb-1">
                                <span class="fw-semibold text-muted">River Level: <strong><?= number_format($dam['river_current_level'], 1) ?>m</strong></span>
                                <span class="text-danger fw-bold">Danger: <?= number_format($dam['river_danger_level'], 1) ?>m</span>
                            </div>
                            <?php 
                                $rLevel = (float)$dam['river_current_level'];
                                $rDanger = (float)$dam['river_danger_level'];
                                $rPct = min(100, round(($rLevel / max(1, $rDanger)) * 100));
                                $rBarClass = $rLevel >= $rDanger ? 'bg-danger' : ($rLevel >= (float)$dam['river_warning_level'] ? 'bg-warning' : 'bg-info');
                            ?>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar <?= $rBarClass ?>" role="progressbar" style="width: <?= $rPct ?>%;"></div>
                            </div>
                            <div class="d-flex justify-content-between text-muted" style="font-size: 0.7rem; margin-top: 2px;">
                                <span>Status: <strong class="<?= $dam['river_status'] === 'Flooding' ? 'text-danger' : 'text-success' ?>"><?= Helper::e($dam['river_status']) ?></strong></span>
                                <span>Warn: <?= number_format($dam['river_warning_level'], 1) ?>m</span>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer bg-transparent border-0 pt-0 pb-3 d-flex justify-content-between align-items-center">
                        <small class="text-muted" style="font-size: 0.7rem;">
                            <i class="bi bi-clock-history me-1"></i><?= Helper::timeAgo($dam['last_synced_at']) ?>
                        </small>
                        <button class="btn btn-outline-primary btn-sm rounded-pill px-3 py-1 btn-refresh-dam" 
                                data-id="<?= $dam['id'] ?>">
                            <i class="bi bi-arrow-repeat me-1"></i> Sync
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Handle Individual Dam Sync Button
    document.querySelectorAll('.btn-refresh-dam').forEach(btn => {
        btn.addEventListener('click', async () => {
            const damId = btn.getAttribute('data-id');
            const origHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

            try {
                const res = await fetch(`${window.APP_CONFIG.baseUrl}/api/dams.php?action=sync&id=${damId}`);
                const json = await res.json();
                if (json.success) {
                    location.reload();
                } else {
                    alert('Sync notice: ' + json.message);
                }
            } catch (err) {
                alert('Sync failed: ' + err.message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = origHtml;
            }
        });
    });
});
</script>

<?php require_once __DIR__ . '/layouts/footer.php'; ?>