<?php
/**
 * Administrator Risk Rule Engine Configurator
 * Satisfies PRD Section 5.2 (FR-05, FR-06) & Section 10
 */

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once dirname(__DIR__, 2) . '/core/Database.php';
require_once dirname(__DIR__, 2) . '/core/Auth.php';
require_once dirname(__DIR__, 2) . '/core/CSRF.php';
require_once dirname(__DIR__, 2) . '/core/Helper.php';
require_once dirname(__DIR__, 2) . '/services/RiskRuleEngine.php';

Auth::requireRole(['Administrator']);

$rule = RiskRuleEngine::getActiveRule();

$pageTitle = "Risk Rules Configuration";
require_once dirname(__DIR__) . '/layouts/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="bi bi-sliders text-warning me-2"></i>Risk Rule Engine &amp; Threshold Tuning
            </h2>
            <p class="text-muted small mb-0">Configure mathematical weights, baseline maximums, and category decision thresholds.</p>
        </div>
        <span class="badge bg-success fs-6 px-3 py-2">
            <i class="bi bi-check-circle-fill me-1"></i> Active Rule: <?= Helper::e($rule['rule_name']) ?>
        </span>
    </div>

    <div class="row g-4">
        <!-- Configuration Form Column -->
        <div class="col-lg-7">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-header bg-dark text-white py-3">
                    <h5 class="fw-bold mb-0 text-white"><i class="bi bi-gear-fill me-2 text-info"></i>Algorithm Parameters</h5>
                </div>
                <div class="card-body p-4">
                    <form id="rulesForm">
                        <?= CSRF::inputField() ?>
                        <input type="hidden" name="id" value="<?= (int)$rule['id'] ?>">

                        <div class="mb-3">
                            <label for="rule_name" class="form-label fw-semibold small">Rule Set Name *</label>
                            <input type="text" class="form-control" id="rule_name" name="rule_name" required 
                                   value="<?= Helper::e($rule['rule_name']) ?>">
                        </div>

                        <hr class="my-4">

                        <h6 class="fw-bold text-primary small text-uppercase mb-3">1. Factor Weight Distribution (Must Sum to 1.0)</h6>
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label for="rainfall_weight" class="form-label fw-semibold small">Rainfall Weight (W<sub>rain</sub>) *</label>
                                <input type="number" step="0.05" min="0" max="1" class="form-control font-monospace" 
                                       id="rainfall_weight" name="rainfall_weight" required 
                                       value="<?= (float)$rule['rainfall_weight'] ?>">
                            </div>
                            <div class="col-6">
                                <label for="river_weight" class="form-label fw-semibold small">River Level Weight (W<sub>river</sub>) *</label>
                                <input type="number" step="0.05" min="0" max="1" class="form-control font-monospace" 
                                       id="river_weight" name="river_weight" required 
                                       value="<?= (float)$rule['river_weight'] ?>">
                            </div>
                        </div>

                        <hr class="my-4">

                        <h6 class="fw-bold text-primary small text-uppercase mb-3">2. Normalization Ceilings (100-Score Baseline)</h6>
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label for="max_rainfall_baseline" class="form-label fw-semibold small">Max Rainfall (mm) *</label>
                                <input type="number" step="1" class="form-control font-monospace" 
                                       id="max_rainfall_baseline" name="max_rainfall_baseline" required 
                                       value="<?= (float)$rule['max_rainfall_baseline'] ?>">
                                <div class="form-text small">Rainfall value corresponding to 100 normalized score.</div>
                            </div>
                            <div class="col-6">
                                <label for="max_river_baseline" class="form-label fw-semibold small">Max River Level (meters) *</label>
                                <input type="number" step="0.1" class="form-control font-monospace" 
                                       id="max_river_baseline" name="max_river_baseline" required 
                                       value="<?= (float)$rule['max_river_baseline'] ?>">
                                <div class="form-text small">River height corresponding to 100 normalized score.</div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <h6 class="fw-bold text-primary small text-uppercase mb-3">3. Category Classification Thresholds</h6>
                        <div class="row g-3 mb-4">
                            <div class="col-4">
                                <label for="low_max_score" class="form-label fw-semibold small">Low &le;</label>
                                <input type="number" step="1" class="form-control font-monospace" 
                                       id="low_max_score" name="low_max_score" required 
                                       value="<?= (float)$rule['low_max_score'] ?>">
                            </div>
                            <div class="col-4">
                                <label for="mod_max_score" class="form-label fw-semibold small">Moderate &le;</label>
                                <input type="number" step="1" class="form-control font-monospace" 
                                       id="mod_max_score" name="mod_max_score" required 
                                       value="<?= (float)$rule['mod_max_score'] ?>">
                            </div>
                            <div class="col-4">
                                <label for="high_max_score" class="form-label fw-semibold small">High &le;</label>
                                <input type="number" step="1" class="form-control font-monospace" 
                                       id="high_max_score" name="high_max_score" required 
                                       value="<?= (float)$rule['high_max_score'] ?>">
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" id="btnSaveRules" class="btn btn-warning btn-lg fw-bold shadow-sm">
                                <i class="bi bi-save me-1"></i> Update Active Risk Formula
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Live Calculation Simulator Column -->
        <div class="col-lg-5">
            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="fw-bold text-dark mb-0"><i class="bi bi-calculator-fill text-primary me-2"></i>Live Rule Engine Simulator</h6>
                    <small class="text-muted">Test how input values map to risk scores in real-time</small>
                </div>
                <div class="card-body pt-0">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Sample Rainfall: <strong id="simRainVal">140</strong> mm</label>
                        <input type="range" class="form-range" id="simRainInput" min="0" max="350" value="140">
                    </div>
                    <div class="mb-4">
                        <label class="form-label small fw-semibold">Sample River Level: <strong id="simRiverVal">9.5</strong> meters</label>
                        <input type="range" class="form-range" id="simRiverInput" min="0" max="20" step="0.1" value="9.5">
                    </div>

                    <!-- Calculated Output Box -->
                    <div class="p-4 bg-light rounded-4 text-center border">
                        <span class="small text-muted text-uppercase fw-bold">Simulated Score</span>
                        <div class="display-4 fw-bold text-dark my-1" id="simScore">--</div>
                        <div class="mb-3">
                            <span class="badge fs-6 px-3 py-1" id="simCategory">--</span>
                        </div>
                        <div class="progress mb-3" style="height: 10px;">
                            <div id="simProgressBar" class="progress-bar" role="progressbar" style="width: 0%;"></div>
                        </div>
                        <div class="small text-muted text-start font-monospace" id="simFormulaBreakdown">
                            <!-- Injected by JS -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    updateSimulator();

    document.getElementById('simRainInput')?.addEventListener('input', (e) => {
        document.getElementById('simRainVal').textContent = e.target.value;
        updateSimulator();
    });

    document.getElementById('simRiverInput')?.addEventListener('input', (e) => {
        document.getElementById('simRiverVal').textContent = e.target.value;
        updateSimulator();
    });

    // Form submission
    document.getElementById('rulesForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = document.getElementById('btnSaveRules');
        btn.disabled = true;

        const formData = new FormData(e.target);
        const payload = Object.fromEntries(formData.entries());

        try {
            const res = await fetch(`${window.APP_CONFIG.baseUrl}/api/manage_rules.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const json = await res.json();
            if (!json.success) throw new Error(json.message || 'Update failed.');

            alert('Risk formula parameters updated successfully.');
            location.reload();
        } catch (err) {
            alert('Rule Update Error: ' + err.message);
        } finally {
            btn.disabled = false;
        }
    });
});

function updateSimulator() {
    const rainMm = parseFloat(document.getElementById('simRainInput')?.value || 0);
    const riverM = parseFloat(document.getElementById('simRiverInput')?.value || 0);

    const wRain = parseFloat(document.getElementById('rainfall_weight')?.value || 0.5);
    const wRiver = parseFloat(document.getElementById('river_weight')?.value || 0.5);
    const maxRain = parseFloat(document.getElementById('max_rainfall_baseline')?.value || 250);
    const maxRiver = parseFloat(document.getElementById('max_river_baseline')?.value || 15);

    const lowMax = parseFloat(document.getElementById('low_max_score')?.value || 25);
    const modMax = parseFloat(document.getElementById('mod_max_score')?.value || 50);
    const highMax = parseFloat(document.getElementById('high_max_score')?.value || 75);

    const normRain = Math.min(100, (rainMm / maxRain) * 100);
    const normRiver = Math.min(100, (riverM / maxRiver) * 100);

    const score = Math.min(100, Math.max(0, (normRain * wRain) + (normRiver * wRiver))).toFixed(1);

    let category = 'Critical';
    let badgeClass = 'bg-danger';
    if (score <= lowMax) {
        category = 'Low';
        badgeClass = 'bg-success';
    } else if (score <= modMax) {
        category = 'Moderate';
        badgeClass = 'bg-info text-dark';
    } else if (score <= highMax) {
        category = 'High';
        badgeClass = 'bg-warning text-dark';
    }

    document.getElementById('simScore').textContent = score;
    const catBadge = document.getElementById('simCategory');
    catBadge.textContent = category;
    catBadge.className = `badge fs-6 px-3 py-1 ${badgeClass}`;

    const pBar = document.getElementById('simProgressBar');
    pBar.style.width = `${score}%`;
    pBar.className = `progress-bar ${badgeClass}`;

    document.getElementById('simFormulaBreakdown').innerHTML = `
        Formula: (${normRain.toFixed(1)} &times; ${wRain}) + (${normRiver.toFixed(1)} &times; ${wRiver}) = <strong>${score}</strong><br>
        Rain norm: ${normRain.toFixed(1)}% | River norm: ${normRiver.toFixed(1)}%
    `;
}
</script>

<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
