<?php
/**
 * Coordinator Relief Camp Capacity & Status Control
 * Satisfies PRD Section 6 and US-07
 */

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once dirname(__DIR__, 2) . '/core/Database.php';
require_once dirname(__DIR__, 2) . '/core/Auth.php';
require_once dirname(__DIR__, 2) . '/core/CSRF.php';
require_once dirname(__DIR__, 2) . '/core/Helper.php';

Auth::requireRole(['Relief Coordinator', 'Administrator']);

$db = Database::getInstance();

$camps = $db->fetchAll("
    SELECT c.*, z.name AS zone_name 
    FROM `camps` c
    LEFT JOIN `zones` z ON c.zone_id = z.id
    WHERE c.active = TRUE
    ORDER BY c.district ASC, c.name ASC
");

// Fetch Recent Capacity Updates History
$recentUpdates = $db->fetchAll("
    SELECT cu.*, c.name AS camp_name, u.full_name AS updated_by_name 
    FROM `camp_updates` cu
    JOIN `camps` c ON cu.camp_id = c.id
    LEFT JOIN `users` u ON cu.updated_by = u.id
    ORDER BY cu.created_at DESC
    LIMIT 8
");

$pageTitle = "Relief Camp Capacity Management";
require_once dirname(__DIR__) . '/layouts/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="bi bi-hospital-fill text-success me-2"></i>Live Shelter Capacity &amp; Occupancy
            </h2>
            <p class="text-muted small mb-0">Update shelter headcounts, available beds, and operational status to prevent citizen overcrowding.</p>
        </div>
        <a href="<?= BASE_URL ?>/views/citizen/camps.php" target="_blank" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-box-arrow-up-right me-1"></i> Preview Public Camp Locator
        </a>
    </div>

    <!-- Camps Update Table Card -->
    <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-5">
        <div class="card-header bg-dark text-white py-3">
            <h5 class="fw-bold mb-0 text-white">Active Relief Shelters (<?= count($camps) ?> Registered)</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th>Shelter &amp; District</th>
                        <th style="width: 130px;">Total Capacity</th>
                        <th style="width: 140px;">Current Occupancy</th>
                        <th>Vacant Beds</th>
                        <th style="width: 160px;">Operational Status</th>
                        <th>Remarks</th>
                        <th class="text-end" style="width: 110px;">Action</th>
                    </tr>
                </thead>
                <tbody class="small">
                    <?php foreach ($camps as $camp): 
                        $cap = (int)$camp['total_capacity'];
                        $occ = (int)$camp['current_occupancy'];
                        $vac = max(0, $cap - $occ);
                    ?>
                        <tr id="camp-row-<?= $camp['id'] ?>">
                            <td>
                                <strong class="text-dark d-block"><?= Helper::e($camp['name']) ?></strong>
                                <small class="text-muted"><i class="bi bi-geo-alt me-1"></i><?= Helper::e($camp['district']) ?> &bull; <?= Helper::e($camp['contact_person']) ?> (<?= Helper::e($camp['contact_phone']) ?>)</small>
                            </td>
                            <td>
                                <span class="badge bg-secondary fs-6 px-3 py-1"><?= $cap ?> beds</span>
                            </td>
                            <td>
                                <input type="number" min="0" max="<?= $cap ?>" class="form-control form-control-sm font-monospace fw-bold" 
                                       id="occ-<?= $camp['id'] ?>" value="<?= $occ ?>">
                            </td>
                            <td>
                                <span id="vac-badge-<?= $camp['id'] ?>" class="badge bg-<?= $vac > 0 ? 'success' : 'danger' ?> fs-6">
                                    <?= $vac ?> free
                                </span>
                            </td>
                            <td>
                                <select class="form-select form-select-sm" id="status-<?= $camp['id'] ?>">
                                    <option value="Operational" <?= $camp['status'] === 'Operational' ? 'selected' : '' ?>>Operational</option>
                                    <option value="Full" <?= $camp['status'] === 'Full' ? 'selected' : '' ?>>Full</option>
                                    <option value="Standby" <?= $camp['status'] === 'Standby' ? 'selected' : '' ?>>Standby</option>
                                    <option value="Closed" <?= $camp['status'] === 'Closed' ? 'selected' : '' ?>>Closed</option>
                                </select>
                            </td>
                            <td>
                                <input type="text" class="form-control form-control-sm" id="remarks-<?= $camp['id'] ?>" 
                                       placeholder="Reason for update...">
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-primary" onclick="saveCampStatus(<?= $camp['id'] ?>, <?= $cap ?>)">
                                    <i class="bi bi-check2"></i> Save
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Capacity Update History Log -->
    <div class="card shadow-sm border-0 rounded-4">
        <div class="card-header bg-white py-3 border-0">
            <h6 class="fw-bold text-dark mb-0"><i class="bi bi-clock-history me-2 text-primary"></i>Recent Occupancy Change Log</h6>
        </div>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th>Timestamp</th>
                        <th>Camp Name</th>
                        <th>Occupancy Change</th>
                        <th>Status Transition</th>
                        <th>Updated By</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody class="small" id="historyLogBody">
                    <?php if (empty($recentUpdates)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-3">No capacity change logs recorded yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentUpdates as $up): ?>
                            <tr>
                                <td class="font-monospace text-muted"><?= Helper::formatDateTime($up['created_at']) ?></td>
                                <td><strong><?= Helper::e($up['camp_name']) ?></strong></td>
                                <td><?= $up['previous_occupancy'] ?> &rarr; <span class="fw-bold"><?= $up['new_occupancy'] ?></span></td>
                                <td><span class="badge bg-secondary"><?= Helper::e($up['previous_status']) ?></span> &rarr; <span class="badge bg-primary"><?= Helper::e($up['new_status']) ?></span></td>
                                <td><?= Helper::e($up['updated_by_name'] ?: 'Officer') ?></td>
                                <td class="text-muted"><?= Helper::e($up['remarks'] ?: '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
async function saveCampStatus(campId, totalCap) {
    const occInput = document.getElementById(`occ-${campId}`);
    const statusSelect = document.getElementById(`status-${campId}`);
    const remarksInput = document.getElementById(`remarks-${campId}`);

    const newOcc = parseInt(occInput.value) || 0;
    const newStatus = statusSelect.value;
    const remarks = remarksInput.value.trim();

    try {
        const res = await fetch(`${window.APP_CONFIG.baseUrl}/api/update_camp.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                camp_id: campId,
                current_occupancy: newOcc,
                status: newStatus,
                remarks: remarks
            })
        });

        const json = await res.json();
        if (!json.success) {
            throw new Error(json.message || 'Failed to update camp.');
        }

        // Update Vacant Badge
        const vac = Math.max(0, totalCap - newOcc);
        const vacBadge = document.getElementById(`vac-badge-${campId}`);
        if (vacBadge) {
            vacBadge.textContent = `${vac} free`;
            vacBadge.className = `badge bg-${vac > 0 ? 'success' : 'danger'} fs-6`;
        }

        remarksInput.value = '';
        alert('Camp capacity and status saved successfully.');

    } catch (err) {
        alert('Update Error: ' + err.message);
    }
}
</script>

<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
