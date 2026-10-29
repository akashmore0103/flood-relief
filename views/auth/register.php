<?php
/**
 * Citizen Registration View
 * Flood Risk Prediction & Relief Camp Locator
 */

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once dirname(__DIR__, 2) . '/config/database.php';
require_once dirname(__DIR__, 2) . '/core/Database.php';
require_once dirname(__DIR__, 2) . '/core/Auth.php';
require_once dirname(__DIR__, 2) . '/core/Helper.php';
require_once dirname(__DIR__, 2) . '/core/CSRF.php';
require_once dirname(__DIR__, 2) . '/core/I18n.php';
require_once dirname(__DIR__, 2) . '/services/DamDataService.php';

if (Auth::check()) {
    Helper::redirect('views/citizen/dashboard.php');
}

$db = Database::getInstance();
$basins = $db->fetchAll("SELECT id, name, river_id FROM `river_basins` ORDER BY name ASC");
if (empty($basins)) {
    $basins = DamDataService::getAllRivers();
}

$selectedBasinId = !empty($basins) ? $basins[0]['id'] : null;
$dams = DamDataService::getAllDams($selectedBasinId ? ($basins[0]['river_id'] ?? $selectedBasinId) : null);

$pageTitle = "Citizen Registration & Targeted Flood Watch Setup";
require_once dirname(__DIR__) . '/layouts/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-9 col-lg-7">
            <div class="card glass-card border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="card-header bg-dark text-white text-center py-4">
                    <div class="d-inline-flex p-3 rounded-circle bg-primary bg-opacity-25 text-info mb-2">
                        <i class="bi bi-shield-fill-check fs-2"></i>
                    </div>
                    <h4 class="fw-bold mb-1">Targeted Citizen Registration</h4>
                    <p class="text-white-50 small mb-0">Set up your geofenced flood watch, waterway alerts & emergency shelter routing</p>
                </div>

                <div class="card-body p-4 p-md-5">
                    <form id="registerForm" novalidate>
                        <?= CSRF::inputField() ?>

                        <h6 class="fw-bold text-secondary text-uppercase small mb-3 tracking-wide">
                            <i class="bi bi-person-badge me-1"></i> Personal Credentials
                        </h6>

                        <div class="mb-3">
                            <label for="full_name" class="form-label fw-semibold small">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="full_name" name="full_name" required placeholder="e.g., Rajesh Patil">
                            <div class="invalid-feedback">Please enter your full name.</div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="email" class="form-label fw-semibold small">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" required 
                                       pattern="^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$"
                                       title="Please enter a valid email address (e.g. user@domain.com)"
                                       placeholder="name@example.com">
                                <div class="invalid-feedback">A valid email address is required for flood alerts.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="phone" class="form-label fw-semibold small">10-Digit Mobile <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted">+91</span>
                                    <input type="tel" class="form-control font-monospace" id="phone" name="phone" required 
                                           pattern="^[6-9][0-9]{9}$" maxlength="10" placeholder="9822012345">
                                </div>
                                <div class="invalid-feedback">Enter a valid 10-digit Indian mobile number.</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label fw-semibold small">Password (Min 6 chars) <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="password" name="password" required minlength="6" placeholder="Create a secure password">
                            <div class="invalid-feedback">Password must be at least 6 characters.</div>
                        </div>

                        <hr class="my-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h6 class="fw-bold text-primary text-uppercase small mb-0">
                                <i class="bi bi-water me-1"></i> Primary Monitored Waterway / Flood Zone
                            </h6>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 small">Geofenced DSS</span>
                        </div>

                        <!-- Cascading Dropdowns: Basin -> Dam -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="monitored_river_id" class="form-label fw-semibold small">River Basin <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm" id="monitored_river_id" name="monitored_river_id" required>
                                    <?php foreach ($basins as $b): ?>
                                        <option value="<?= $b['id'] ?>" data-river-id="<?= $b['river_id'] ?? $b['id'] ?>">
                                            <?= Helper::e($b['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text small text-muted">Selecting basin filters upstream/downstream dams.</div>
                            </div>
                            <div class="col-md-6">
                                <label for="monitored_dam_id" class="form-label fw-semibold small">Monitored Dam / Reservoir <span class="text-danger">*</span></label>
                                <div class="position-relative">
                                    <select class="form-select form-select-sm" id="monitored_dam_id" name="monitored_dam_id" required>
                                        <?php if (!empty($dams)): ?>
                                            <?php foreach ($dams as $d): ?>
                                                <option value="<?= $d['id'] ?>">
                                                    <?= Helper::e($d['name']) ?> (<?= Helper::e($d['district'] ?? $d['river_name']) ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <option value="">No dams registered in this basin</option>
                                        <?php endif; ?>
                                    </select>
                                    <div id="damLoadingSpinner" class="spinner-border spinner-border-sm text-primary position-absolute top-50 end-0 translate-middle-y me-4 d-none" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                </div>
                                <div class="form-text small text-muted">Your shelter routing links directly to this dam.</div>
                            </div>
                        </div>

                        <!-- Geographic & Address Inputs -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="district" class="form-label fw-semibold small">District <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm" id="district" name="district" required
                                       placeholder="e.g., Nashik, Satara, Solapur, Ahilyanagar">
                            </div>
                            <div class="col-md-6">
                                <label for="village_city" class="form-label fw-semibold small">Locality / Village / Ward</label>
                                <input type="text" class="form-control form-control-sm" id="village_city" name="village_city" 
                                       placeholder="e.g., Anandwalli / Paithan Ward 4">
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="address" class="form-label fw-semibold small">Residential / Evacuation Address</label>
                            <textarea class="form-control form-control-sm" id="address" name="address" rows="2" 
                                      placeholder="Street name, landmark, near river ghat / canal line..."></textarea>
                            <div class="form-text small text-muted">Used by coordinators during red alert evacuation operations.</div>
                        </div>

                        <div class="d-grid mb-3">
                            <button type="submit" id="btnRegister" class="btn btn-primary btn-lg fw-bold shadow-sm">
                                <i class="bi bi-shield-lock-fill me-2"></i> Register &amp; Activate Flood Watch
                            </button>
                        </div>

                        <div class="text-center small text-muted">
                            Already registered? 
                            <a href="<?= BASE_URL ?>/views/auth/login.php" class="fw-bold text-decoration-none text-primary">
                                Sign in here
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const basinSelect = document.getElementById('monitored_river_id');
    const damSelect = document.getElementById('monitored_dam_id');
    const damSpinner = document.getElementById('damLoadingSpinner');
    const form = document.getElementById('registerForm');
    const btn = document.getElementById('btnRegister');

    // Dynamic Cascading Basin -> Dam AJAX Loader
    async function loadDamsForBasin(basinId) {
        if (!basinId) return;
        damSpinner.classList.remove('d-none');
        damSelect.disabled = true;

        try {
            const res = await fetch(`${window.APP_CONFIG.baseUrl}/api/telemetry.php?action=dams&basin_id=${encodeURIComponent(basinId)}`);
            const data = await res.json();

            damSelect.innerHTML = '';
            if (data.success && data.data && data.data.length > 0) {
                data.data.forEach(dam => {
                    const opt = document.createElement('option');
                    opt.value = dam.id;
                    opt.textContent = `${dam.name} (${dam.district || dam.river_name || 'Reservoir'})`;
                    damSelect.appendChild(opt);
                });
            } else {
                const opt = document.createElement('option');
                opt.value = '';
                opt.textContent = 'No dams found in this basin';
                damSelect.appendChild(opt);
            }
        } catch (err) {
            console.error('Failed to fetch dams for basin:', err);
        } finally {
            damSpinner.classList.add('d-none');
            damSelect.disabled = false;
        }
    }

    if (basinSelect) {
        basinSelect.addEventListener('change', () => {
            loadDamsForBasin(basinSelect.value);
        });
    }

    // Form submission with strict email regex check
    form?.addEventListener('submit', async (e) => {
        e.preventDefault();

        const emailInput = document.getElementById('email');
        const emailRegex = /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/;
        
        if (!emailRegex.test(emailInput.value.trim())) {
            emailInput.classList.add('is-invalid');
            emailInput.focus();
            return;
        } else {
            emailInput.classList.remove('is-invalid');
        }

        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return;
        }

        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Setting Up Flood Watch...';

        const payload = Object.fromEntries(new FormData(form).entries());

        try {
            const res = await fetch(`${window.APP_CONFIG.baseUrl}/api/auth.php?action=register`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const json = await res.json();
            if (json.success) {
                window.location.href = json.data.redirect || `${window.APP_CONFIG.baseUrl}/views/citizen/dashboard.php`;
            } else {
                alert('Registration Notice: ' + json.message);
            }
        } catch (err) {
            alert('Request Failed: ' + err.message);
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-shield-lock-fill me-2"></i> Register &amp; Activate Flood Watch';
        }
    });
});
</script>

<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
