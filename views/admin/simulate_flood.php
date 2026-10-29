<?php
/**
 * Admin Dummy Flood Simulation Studio
 * Designed for MCA Academic Project Viva Presentations & Defense
 * Simulates extreme rainfall, emergency reservoir discharges, and downstream floodplain inundation
 * Flood Risk Prediction & Relief Camp Locator
 */

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once dirname(__DIR__, 2) . '/core/Database.php';
require_once dirname(__DIR__, 2) . '/core/Auth.php';
require_once dirname(__DIR__, 2) . '/core/Helper.php';
require_once dirname(__DIR__, 2) . '/core/CSRF.php';
require_once dirname(__DIR__, 2) . '/services/DamDataService.php';
require_once dirname(__DIR__, 2) . '/services/SimulationService.php';

Auth::requireRole(['Administrator']);

$db = Database::getInstance();
$dams = DamDataService::getAllDams();
$activeSim = SimulationService::getActiveSimulation();
$recentLogs = SimulationService::getRecentLogs(8);

$pageTitle = "Dummy Flood Simulation Studio (Viva Demo)";
require_once dirname(__DIR__) . '/layouts/header.php';
?>

<div class="container py-4">
    <!-- Header Console Banner -->
    <div class="emergency-hud mb-4">
        <div class="row align-items-center">
            <div class="col-lg-8">
                <span class="badge bg-warning text-dark px-3 py-1 mb-2 font-monospace fw-bold">
                    <i class="bi bi-lightning-charge-fill me-1"></i> ACADEMIC VIVA DEMO SIMULATOR
                </span>
                <h2 class="fw-bold mb-1">Dummy Flood Simulation Studio</h2>
                <p class="mb-0 text-white-50 small">
                    Trigger simulated extreme precipitation and massive reservoir gate discharges to demonstrate real-time telemetry changes, rule-based algorithmic risk escalation, and automated citizen evacuation warnings.
                </p>
            </div>
            <div class="col-lg-4 text-lg-end mt-3 mt-lg-0">
                <?php if ($activeSim): ?>
                    <span class="badge bg-danger pulse-emergency px-3 py-2 fs-6">
                        <i class="bi bi-exclamation-octagon-fill me-1"></i> SIMULATION ACTIVE: FLASH FLOOD
                    </span>
                <?php else: ?>
                    <span class="badge bg-success px-3 py-2 fs-6">
                        <i class="bi bi-check-circle-fill me-1"></i> BASELINE TELEMETRY NORMAL
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <!-- Interactive Control Console -->
        <div class="col-lg-6">
            <div class="card glass-card h-100 border-0 shadow-sm rounded-4">
                <div class="card-header bg-dark text-white py-3 rounded-top-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold mb-0">
                            <i class="bi bi-sliders2 text-warning me-2"></i>Simulation Parameters
                        </h5>
                        <span class="badge bg-secondary font-monospace">Real-time Hook</span>
                    </div>
                </div>

                <div class="card-body p-4">
                    <form id="simulationForm">
                        <?= CSRF::inputField() ?>

                        <!-- Target Dam Selection -->
                        <div class="mb-4">
                            <label for="dam_id" class="form-label fw-bold text-dark small text-uppercase">
                                1. Target Reservoir / Dam *
                            </label>
                            <select class="form-select form-select-lg" id="dam_id" name="dam_id" required>
                                <?php foreach ($dams as $d): ?>
                                    <option value="<?= $d['id'] ?>" data-river="<?= Helper::e($d['river_name']) ?>" data-cap="<?= $d['gross_capacity_tmc'] ?>">
                                        <?= Helper::e($d['name']) ?> (<?= Helper::e($d['river_name']) ?> River - <?= $d['gross_capacity_tmc'] ?> TMC)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text small">Discharge from this dam will directly flood its downstream river basin.</div>
                        </div>

                        <!-- Rainfall Slider -->
                        <div class="mb-4 p-3 bg-light rounded-3 border">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label for="rainfall_slider" class="form-label fw-bold text-dark small text-uppercase mb-0">
                                    2. Simulated Extreme Rainfall (mm/hr)
                                </label>
                                <span class="badge bg-primary fs-6 font-monospace" id="rainfallDisplay">135 mm/hr</span>
                            </div>
                            <input type="range" class="form-range simulation-slider" id="rainfall_slider" name="rainfall_mm" 
                                   min="50" max="250" step="5" value="135" oninput="updateRainfallVal(this.value)">
                            <div class="d-flex justify-content-between text-muted" style="font-size: 0.72rem;">
                                <span>50 mm/hr (Heavy Rain)</span>
                                <span>150 mm/hr (Cloudburst)</span>
                                <span>250 mm/hr (Catastrophic)</span>
                            </div>
                        </div>

                        <!-- Dam Discharge Slider -->
                        <div class="mb-4 p-3 bg-light rounded-3 border">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label for="discharge_slider" class="form-label fw-bold text-dark small text-uppercase mb-0">
                                    3. Emergency Dam Discharge (Outflow cusecs)
                                </label>
                                <span class="badge bg-danger fs-6 font-monospace" id="dischargeDisplay">125,000 cusecs</span>
                            </div>
                            <input type="range" class="form-range simulation-slider" id="discharge_slider" name="discharge_cusecs" 
                                   min="25000" max="300000" step="5000" value="125000" oninput="updateDischargeVal(this.value)">
                            <div class="d-flex justify-content-between text-muted" style="font-size: 0.72rem;">
                                <span>25,000 cusecs (Warning)</span>
                                <span>150,000 cusecs (Danger)</span>
                                <span>300,000 cusecs (Flash Flood Surge)</span>
                            </div>
                        </div>

                        <!-- Scenario Description -->
                        <div class="mb-4">
                            <label for="scenario_name" class="form-label fw-bold text-dark small text-uppercase">
                                4. Scenario Title
                            </label>
                            <input type="text" class="form-control" id="scenario_name" name="scenario_name" 
                                   value="Severe Cloudburst &amp; 18-Gate Emergency Discharge" required>
                        </div>

                        <!-- Actions Grid -->
                        <div class="row g-2 pt-2">
                            <div class="col-8">
                                <button type="button" id="btnTriggerFlood" class="btn btn-danger btn-lg w-100 fw-bold shadow-sm">
                                    <i class="bi bi-lightning-charge-fill me-2"></i> Trigger Flash Flood
                                </button>
                            </div>
                            <div class="col-4">
                                <button type="button" id="btnResetSimulation" class="btn btn-outline-secondary btn-lg w-100 fw-bold">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Simulation Feedback & Live Impact Preview -->
        <div class="col-lg-6">
            <div class="card glass-card h-100 border-0 shadow-sm rounded-4">
                <div class="card-header bg-white py-3 rounded-top-4 border-0">
                    <h5 class="fw-bold text-dark mb-0">
                        <i class="bi bi-broadcast text-danger me-2"></i>Downstream Impact Preview
                    </h5>
                    <small class="text-muted">Instant telemetry modifications effected upon trigger</small>
                </div>
                <div class="card-body pt-0">
                    <div class="alert alert-warning border-warning-subtle small mb-3">
                        <i class="bi bi-info-circle-fill me-1"></i> <strong>Examiner Note:</strong> When "Trigger Flash Flood" is executed, the following actions occur transactionally:
                        <ul class="mb-0 mt-1 ps-3">
                            <li>Selected dam capacity escalates to 98.5% with all 18 flood gates opened.</li>
                            <li>River basin water level jumps above danger threshold (+1.85m).</li>
                            <li>Connected downstream zones automatically transition to <strong>Critical / Red Alert</strong> (Score 95.5+).</li>
                            <li>Automated Emergency Alert broadcast is dispatched into citizen dashboard feeds.</li>
                        </ul>
                    </div>

                    <!-- Live Active State Card -->
                    <div id="activeStateBox" class="p-4 rounded-4 mb-3 <?= $activeSim ? 'bg-danger text-white' : 'bg-light text-dark border' ?>">
                        <?php if ($activeSim): ?>
                            <h5 class="fw-bold mb-1"><i class="bi bi-exclamation-triangle-fill me-2"></i>Flash Flood Simulation Running</h5>
                            <div class="small opacity-75 mb-3"><?= Helper::e($activeSim['scenario_name']) ?></div>
                            <div class="row g-2 text-start">
                                <div class="col-6">
                                    <div class="p-2 bg-white bg-opacity-25 rounded-3">
                                        <small class="d-block" style="font-size: 0.72rem;">Target Dam</small>
                                        <strong><?= Helper::e($activeSim['dam_name']) ?></strong>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-2 bg-white bg-opacity-25 rounded-3">
                                        <small class="d-block" style="font-size: 0.72rem;">River Basin</small>
                                        <strong><?= Helper::e($activeSim['river_name']) ?></strong>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-2 bg-white bg-opacity-25 rounded-3">
                                        <small class="d-block" style="font-size: 0.72rem;">Rainfall Intensity</small>
                                        <strong><?= $activeSim['simulated_rainfall_mm'] ?> mm/hr</strong>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="p-2 bg-white bg-opacity-25 rounded-3">
                                        <small class="d-block" style="font-size: 0.72rem;">Dam Discharge</small>
                                        <strong><?= number_format($activeSim['simulated_discharge_cusecs']) ?> cusecs</strong>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="bi bi-shield-check display-4 text-success d-block mb-2"></i>
                                <h6 class="fw-bold text-dark">No Active Simulation</h6>
                                <p class="text-muted small mb-0">Select a dam and parameters on the left, then click "Trigger Flash Flood" to start the viva demonstration.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex gap-2">
                        <a href="<?= BASE_URL ?>/views/citizen/index.php" target="_blank" class="btn btn-outline-primary btn-sm rounded-pill flex-fill">
                            <i class="bi bi-speedometer2 me-1"></i> View Live Risk Map &rarr;
                        </a>
                        <a href="<?= BASE_URL ?>/views/citizen/dashboard.php" target="_blank" class="btn btn-outline-dark btn-sm rounded-pill flex-fill">
                            <i class="bi bi-person-badge me-1"></i> View Citizen Watch &rarr;
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Simulation Audit Log -->
    <div class="card glass-card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="card-header bg-transparent border-0 pt-4 px-4 pb-2">
            <h5 class="fw-bold text-dark mb-0">
                <i class="bi bi-clock-history text-primary me-2"></i>Simulation Execution Log
            </h5>
            <small class="text-muted">Historical archive of simulated hydrological trials</small>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small">
                        <tr>
                            <th class="ps-4">Scenario</th>
                            <th>Target Reservoir</th>
                            <th>Simulated Rain</th>
                            <th>Simulated Discharge</th>
                            <th>Status</th>
                            <th>Triggered By</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php if (empty($recentLogs)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">No simulation trials recorded yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($recentLogs as $log): ?>
                                <tr>
                                    <td class="ps-4 fw-semibold text-dark"><?= Helper::e($log['scenario_name']) ?></td>
                                    <td><?= Helper::e($log['dam_name']) ?> (<?= Helper::e($log['river_name']) ?>)</td>
                                    <td><?= $log['simulated_rainfall_mm'] ?> mm/hr</td>
                                    <td><strong class="text-danger"><?= number_format($log['simulated_discharge_cusecs']) ?></strong> cusecs</td>
                                    <td>
                                        <span class="badge bg-<?= $log['status'] === 'Active' ? 'danger pulse-emergency' : 'secondary' ?>">
                                            <?= Helper::e($log['status']) ?>
                                        </span>
                                    </td>
                                    <td class="text-muted"><?= Helper::e($log['triggered_by_name'] ?? 'Admin') ?></td>
                                    <td class="text-muted"><?= Helper::timeAgo($log['created_at']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function updateRainfallVal(val) {
    document.getElementById('rainfallDisplay').textContent = val + ' mm/hr';
}

function updateDischargeVal(val) {
    document.getElementById('dischargeDisplay').textContent = parseInt(val).toLocaleString() + ' cusecs';
}

document.addEventListener('DOMContentLoaded', () => {
    // Trigger Flash Flood
    document.getElementById('btnTriggerFlood')?.addEventListener('click', async () => {
        const damId = document.getElementById('dam_id').value;
        const rainfall = document.getElementById('rainfall_slider').value;
        const discharge = document.getElementById('discharge_slider').value;
        const scenario = document.getElementById('scenario_name').value;
        const btn = document.getElementById('btnTriggerFlood');

        if (!confirm('Are you sure you want to trigger this simulated flash flood event? Downstream zones will escalate to Critical immediately.')) {
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Escalating Telemetry...';

        try {
            const res = await fetch(`${window.APP_CONFIG.baseUrl}/api/simulate.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    action: 'trigger',
                    dam_id: parseInt(damId),
                    rainfall_mm: parseFloat(rainfall),
                    discharge_cusecs: parseFloat(discharge),
                    scenario_name: scenario
                })
            });

            const json = await res.json();
            if (json.success) {
                alert('⚡ FLASH FLOOD SIMULATION TRIGGERED!\n\n' + json.message + '\n\nOpen the Live Risk Map or Citizen Watch in a new tab to see pulsing red alert markers and emergency banners.');
                location.reload();
            } else {
                alert('Simulation Error: ' + json.message);
            }
        } catch (err) {
            alert('Simulation Request Failed: ' + err.message);
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-lightning-charge-fill me-2"></i> Trigger Flash Flood';
        }
    });

    // Reset Simulation
    document.getElementById('btnResetSimulation')?.addEventListener('click', async () => {
        const btn = document.getElementById('btnResetSimulation');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

        try {
            const res = await fetch(`${window.APP_CONFIG.baseUrl}/api/simulate.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ action: 'reset' })
            });

            const json = await res.json();
            if (json.success) {
                alert('Simulation Reset: All dam discharges and river water levels restored to safe baseline.');
                location.reload();
            } else {
                alert('Reset Error: ' + json.message);
            }
        } catch (err) {
            alert('Reset Request Failed: ' + err.message);
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-arrow-counterclockwise me-1"></i> Reset';
        }
    });
});
</script>

<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
