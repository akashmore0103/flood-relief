<?php
/**
 * Administrator Zone Management View
 * Satisfies PRD Section 5.1 (FR-01, FR-02)
 */

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once dirname(__DIR__, 2) . '/core/Database.php';
require_once dirname(__DIR__, 2) . '/core/Auth.php';
require_once dirname(__DIR__, 2) . '/core/CSRF.php';
require_once dirname(__DIR__, 2) . '/core/Helper.php';

Auth::requireRole(['Administrator']);

$db = Database::getInstance();

$zones = $db->fetchAll("
    SELECT z.*, rl.score, rl.category, rl.observed_at 
    FROM `zones` z
    LEFT JOIN `risk_levels` rl ON rl.id = (
        SELECT id FROM `risk_levels` WHERE zone_id = z.id ORDER BY observed_at DESC, id DESC LIMIT 1
    )
    ORDER BY z.name ASC
");

$pageTitle = "Zone Management";
require_once dirname(__DIR__) . '/layouts/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="bi bi-map-fill text-primary me-2"></i>Disaster Monitoring Zones
            </h2>
            <p class="text-muted small mb-0">Maintain geographic areas, watershed centers, and optional polygon boundary geofencing.</p>
        </div>
        <button class="btn btn-primary shadow-sm" onclick="openZoneModal()">
            <i class="bi bi-plus-circle me-1"></i> Add New Disaster Zone
        </button>
    </div>

    <!-- Zones Table Card -->
    <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark small">
                    <tr>
                        <th>Zone Name</th>
                        <th>District &amp; State</th>
                        <th>Center Coordinates</th>
                        <th>Latest Risk Level</th>
                        <th>Operating Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody class="small">
                    <?php foreach ($zones as $zone): ?>
                        <tr>
                            <td>
                                <strong class="text-dark d-block"><?= Helper::e($zone['name']) ?></strong>
                                <small class="text-muted">ID: #<?= (int)$zone['id'] ?></small>
                            </td>
                            <td><?= Helper::e($zone['district']) ?>, <?= Helper::e($zone['state']) ?></td>
                            <td class="font-monospace text-muted">
                                <?= (float)$zone['latitude'] ?>°, <?= (float)$zone['longitude'] ?>°
                                <a href="https://www.google.com/maps?q=<?= (float)$zone['latitude'] ?>,<?= (float)$zone['longitude'] ?>" target="_blank" class="ms-1 text-info text-decoration-none">
                                    <i class="bi bi-box-arrow-up-right"></i>
                                </a>
                            </td>
                            <td>
                                <span class="badge bg-<?= Helper::getRiskBadgeClass($zone['category']) ?>">
                                    <?= Helper::e($zone['category'] ?? 'Low') ?> (<?= (float)($zone['score'] ?? 0) ?>)
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?= $zone['status'] === 'active' ? 'success' : 'secondary' ?>">
                                    <?= ucfirst($zone['status']) ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-secondary" onclick='openZoneModal(<?= json_encode($zone) ?>)'>
                                    <i class="bi bi-pencil"></i> Edit
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Zone Add/Edit Modal -->
<div class="modal fade" id="zoneModal" tabindex="-1" aria-labelledby="zoneModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-dark text-white py-3">
                <h5 class="modal-title fw-bold" id="zoneModalLabel">Disaster Monitoring Zone</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="zoneForm">
                <?= CSRF::inputField() ?>
                <input type="hidden" id="zone_id" name="id" value="">

                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="zone_name" class="form-label fw-semibold small">Zone Name *</label>
                        <input type="text" class="form-control" id="zone_name" name="name" required placeholder="E.g., Aluva - Lower Periyar Basin">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label for="zone_district" class="form-label fw-semibold small">District *</label>
                            <input type="text" class="form-control" id="zone_district" name="district" required placeholder="E.g., Ernakulam">
                        </div>
                        <div class="col-6">
                            <label for="zone_state" class="form-label fw-semibold small">State *</label>
                            <input type="text" class="form-control" id="zone_state" name="state" required value="Kerala">
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label for="zone_lat" class="form-label fw-semibold small">Center Latitude *</label>
                            <input type="number" step="any" class="form-control font-monospace" id="zone_lat" name="latitude" required placeholder="10.0159">
                        </div>
                        <div class="col-6">
                            <label for="zone_lng" class="form-label fw-semibold small">Center Longitude *</label>
                            <input type="number" step="any" class="form-control font-monospace" id="zone_lng" name="longitude" required placeholder="76.3419">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="zone_geojson" class="form-label fw-semibold small">Boundary GeoJSON (Optional Polygon)</label>
                        <textarea class="form-control font-monospace small" id="zone_geojson" name="boundary_geojson" rows="2" placeholder='{"type":"Polygon", ...}'></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="zone_status" class="form-label fw-semibold small">Monitoring Status</label>
                        <select class="form-select" id="zone_status" name="status">
                            <option value="active">Active (Monitored on Dashboard)</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="btnSaveZone" class="btn btn-primary btn-sm fw-semibold">
                        <i class="bi bi-check2-circle me-1"></i> Save Zone
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let zoneModalInstance = null;

function openZoneModal(zone = null) {
    document.getElementById('zone_id').value = zone ? zone.id : '';
    document.getElementById('zone_name').value = zone ? zone.name : '';
    document.getElementById('zone_district').value = zone ? zone.district : '';
    document.getElementById('zone_state').value = zone ? zone.state : 'Kerala';
    document.getElementById('zone_lat').value = zone ? zone.latitude : '';
    document.getElementById('zone_lng').value = zone ? zone.longitude : '';
    document.getElementById('zone_geojson').value = zone ? (zone.boundary_geojson || '') : '';
    document.getElementById('zone_status').value = zone ? zone.status : 'active';

    document.getElementById('zoneModalLabel').textContent = zone ? 'Edit Disaster Zone' : 'Add New Disaster Zone';
    zoneModalInstance = new bootstrap.Modal(document.getElementById('zoneModal'));
    zoneModalInstance.show();
}

document.getElementById('zoneForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('btnSaveZone');
    btn.disabled = true;

    const formData = new FormData(e.target);
    const payload = Object.fromEntries(formData.entries());

    try {
        const res = await fetch(`${window.APP_CONFIG.baseUrl}/api/zones.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to save zone.');

        alert('Disaster Zone saved successfully.');
        location.reload();
    } catch (err) {
        alert('Save Error: ' + err.message);
    } finally {
        btn.disabled = false;
    }
});
</script>

<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
