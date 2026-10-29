<?php
/**
 * Administrator Emergency Alert Broadcast Studio
 * Satisfies PRD Section 5.5 (FR-17 to FR-20, US-06)
 */

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once dirname(__DIR__, 2) . '/core/Database.php';
require_once dirname(__DIR__, 2) . '/core/Auth.php';
require_once dirname(__DIR__, 2) . '/core/CSRF.php';
require_once dirname(__DIR__, 2) . '/core/Helper.php';

Auth::requireRole(['Administrator']);

$db = Database::getInstance();

$zones = $db->fetchAll("SELECT `id`, `name`, `district` FROM `zones` WHERE `status` = 'active' ORDER BY `name` ASC");

$alerts = $db->fetchAll("
    SELECT a.*, z.name AS zone_name, u.full_name AS created_by_name,
           (SELECT COUNT(*) FROM `alert_delivery_logs` WHERE alert_id = a.id) AS delivery_count
    FROM `alerts` a
    LEFT JOIN `zones` z ON a.target_zone_id = z.id
    LEFT JOIN `users` u ON a.created_by = u.id
    ORDER BY a.created_at DESC
");

$pageTitle = "Broadcast Emergency Alerts";
require_once dirname(__DIR__) . '/layouts/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="bi bi-broadcast text-danger me-2"></i>Emergency Alert Broadcast Studio
            </h2>
            <p class="text-muted small mb-0">Dispatch official evacuation orders, flash flood warnings, and advisory bulletins to citizens.</p>
        </div>
        <a href="<?= BASE_URL ?>/views/citizen/alerts.php" target="_blank" class="btn btn-outline-danger btn-sm">
            <i class="bi bi-eye me-1"></i> Preview Public Bulletin Board
        </a>
    </div>

    <div class="row g-4 mb-5">
        <!-- Alert Composer Form -->
        <div class="col-lg-7">
            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-header bg-dark text-white py-3">
                    <h5 class="fw-bold mb-0 text-white"><i class="bi bi-megaphone-fill me-2 text-danger"></i>Compose Emergency Broadcast</h5>
                </div>
                <div class="card-body p-4">
                    <form id="alertForm">
                        <?= CSRF::inputField() ?>

                        <div class="mb-3">
                            <label for="alert_title" class="form-label fw-semibold small">Alert Title / Headline *</label>
                            <input type="text" class="form-control" id="alert_title" name="title" required 
                                   placeholder="E.g., RED ALERT: Immediate Evacuation Order for Lower Periyar Basin">
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="alert_severity" class="form-label fw-semibold small">Severity Classification *</label>
                                <select class="form-select fw-semibold" id="alert_severity" name="severity" required>
                                    <option value="Critical Danger" class="text-danger fw-bold" selected>Critical Danger (Immediate Rescue/Evacuation)</option>
                                    <option value="Evacuation" class="text-danger fw-bold">Evacuation Order</option>
                                    <option value="Warning" class="text-warning fw-bold">Warning (Elevated Risk)</option>
                                    <option value="Advisory" class="text-info">Advisory (Informational)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="target_zone_id" class="form-label fw-semibold small">Target Disaster Area *</label>
                                <select class="form-select" id="target_zone_id" name="target_zone_id">
                                    <option value="">-- All Monitored Zones (Statewide Broadcast) --</option>
                                    <?php foreach ($zones as $z): ?>
                                        <option value="<?= $z['id'] ?>"><?= Helper::e($z['name']) ?> (<?= Helper::e($z['district']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="alert_message" class="form-label fw-semibold small">Detailed Directives &amp; Public Instructions *</label>
                            <textarea class="form-control" id="alert_message" name="message" rows="4" required 
                                      placeholder="Clearly explain the imminent hazard, evacuation routes, nearest operational shelters, and official helpline numbers..."></textarea>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="alert_channel" class="form-label fw-semibold small">Dispatch Channels</label>
                                <select class="form-select" id="alert_channel" name="channel">
                                    <option value="All" selected>All (In-App Banner + SMS + Email Simulation)</option>
                                    <option value="In-App">In-App Banner Only</option>
                                    <option value="SMS">SMS Gateway Simulation</option>
                                    <option value="Email">Email Dispatcher</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="expires_at" class="form-label fw-semibold small">Expiry Timestamp (Optional)</label>
                                <input type="datetime-local" class="form-control" id="expires_at" name="expires_at" 
                                       value="<?= date('Y-m-d\TH:i', strtotime('+48 hours')) ?>">
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" id="btnBroadcast" class="btn btn-danger btn-lg fw-bold shadow-sm">
                                <i class="bi bi-broadcast me-2"></i> Authorize &amp; Broadcast Alert
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Live Citizen Preview Card -->
        <div class="col-lg-5">
            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-header bg-white py-3 border-0">
                    <h6 class="fw-bold text-dark mb-0"><i class="bi bi-display me-2 text-primary"></i>Citizen Display Preview</h6>
                    <small class="text-muted">How this alert will appear on public portals and phones</small>
                </div>
                <div class="card-body pt-0">
                    <div class="p-3 bg-danger text-white rounded-3 shadow-sm mb-3" id="previewBanner">
                        <div class="d-flex align-items-center mb-1">
                            <span class="badge bg-light text-danger fw-bold me-2 px-2 py-1">
                                <i class="bi bi-exclamation-octagon-fill me-1"></i> EMERGENCY ALERT
                            </span>
                            <span class="small" id="previewZoneTag">Statewide</span>
                        </div>
                        <h6 class="fw-bold mb-1" id="previewTitle">Alert Title will render here...</h6>
                        <p class="small mb-0 text-white-50" id="previewMsg">Your public directives and evacuation guidance will appear here.</p>
                    </div>
                    <small class="text-muted d-block">
                        <i class="bi bi-shield-check me-1 text-success"></i> Strict RBAC enforced: Only authenticated Administrators can trigger broadcasts.
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert Broadcast History Table -->
    <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
        <div class="card-header bg-white py-3 border-0">
            <h5 class="fw-bold text-dark mb-0"><i class="bi bi-clock-history me-2 text-primary"></i>Broadcast History Log</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light small">
                    <tr>
                        <th>Date &amp; Time</th>
                        <th>Severity &amp; Title</th>
                        <th>Target Area</th>
                        <th>Channel</th>
                        <th>Status</th>
                        <th>Dispatches</th>
                        <th>Author</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody class="small">
                    <?php if (empty($alerts)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted">No previous broadcasts on record.</td></tr>
                    <?php else: ?>
                        <?php foreach ($alerts as $a): ?>
                            <tr id="alert-row-<?= $a['id'] ?>">
                                <td class="font-monospace text-muted"><?= Helper::formatDateTime($a['created_at']) ?></td>
                                <td>
                                    <span class="badge bg-<?= match($a['severity']) {
                                        'Critical Danger' => 'danger',
                                        'Evacuation' => 'danger',
                                        'Warning' => 'warning text-dark',
                                        default => 'info text-dark'
                                    } ?> mb-1">
                                        <?= Helper::e($a['severity']) ?>
                                    </span>
                                    <strong class="text-dark d-block"><?= Helper::e($a['title']) ?></strong>
                                </td>
                                <td><?= !empty($a['zone_name']) ? Helper::e($a['zone_name']) : 'All Zones' ?></td>
                                <td><span class="badge bg-secondary"><?= Helper::e($a['channel']) ?></span></td>
                                <td>
                                    <span class="badge bg-<?= ($a['status'] === 'Published' && ($a['is_active'] ?? 1) == 1) ? 'success' : 'secondary' ?>">
                                        <?= ($a['status'] === 'Published' && ($a['is_active'] ?? 1) == 1) ? 'Active / Published' : Helper::e($a['status']) ?>
                                    </span>
                                </td>
                                <td class="text-center font-monospace"><?= (int)$a['delivery_count'] ?> logs</td>
                                <td><?= Helper::e($a['created_by_name'] ?: 'Admin') ?></td>
                                <td class="text-end">
                                    <button type="button" 
                                            class="btn btn-outline-danger btn-sm rounded-pill py-1 px-3 btn-delete-alert fw-semibold" 
                                            data-alert-id="<?= $a['id'] ?>" 
                                            data-alert-title="<?= Helper::e($a['title']) ?>">
                                        <i class="bi bi-trash3-fill me-1"></i> Revoke / Delete
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const titleInput = document.getElementById('alert_title');
    const msgInput = document.getElementById('alert_message');
    const zoneSelect = document.getElementById('target_zone_id');

    function updatePreview() {
        document.getElementById('previewTitle').textContent = titleInput.value || 'Alert Title will render here...';
        document.getElementById('previewMsg').textContent = msgInput.value || 'Your public directives will appear here.';
        document.getElementById('previewZoneTag').textContent = zoneSelect.options[zoneSelect.selectedIndex].text;
    }

    titleInput.addEventListener('input', updatePreview);
    msgInput.addEventListener('input', updatePreview);
    zoneSelect.addEventListener('change', updatePreview);

    document.getElementById('alertForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const btn = document.getElementById('btnBroadcast');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Transmitting Broadcast...';

        const formData = new FormData(e.target);
        const payload = Object.fromEntries(formData.entries());

        try {
            const res = await fetch(`${window.APP_CONFIG.baseUrl}/api/alerts.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const json = await res.json();
            if (!json.success) throw new Error(json.message || 'Broadcast failed.');

            alert('Emergency alert broadcasted successfully across designated channels.');
            location.reload();

        } catch (err) {
            alert('Broadcast Error: ' + err.message);
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-broadcast me-2"></i> Authorize & Broadcast Alert';
        }
    });

    // Revoke / Delete Emergency Alert Handler
    document.addEventListener('click', async (e) => {
        const deleteBtn = e.target.closest('.btn-delete-alert');
        if (!deleteBtn) return;

        const alertId = deleteBtn.getAttribute('data-alert-id');
        const alertTitle = deleteBtn.getAttribute('data-alert-title') || `Alert #${alertId}`;

        if (!confirm(`Are you sure you want to revoke and delete emergency alert #${alertId}?\n"${alertTitle}"\n\nThis will remove it immediately from all citizen safety feeds.`)) {
            return;
        }

        const originalHtml = deleteBtn.innerHTML;
        deleteBtn.disabled = true;
        deleteBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Deleting...';

        try {
            const res = await fetch(`${window.APP_CONFIG.baseUrl}/api/alerts.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    action: 'delete',
                    alert_id: alertId
                })
            });

            const json = await res.json();
            if (json.success) {
                const tr = document.getElementById(`alert-row-${alertId}`) || deleteBtn.closest('tr');
                if (tr) {
                    tr.style.transition = 'all 0.35s ease';
                    tr.style.opacity = '0';
                    tr.style.transform = 'scale(0.95)';
                    setTimeout(() => tr.remove(), 350);
                }

                const toastTitle = document.getElementById('toastAlertTitle');
                const toastMsg = document.getElementById('toastAlertMessage');
                if (toastTitle) toastTitle.textContent = 'Emergency Alert Revoked';
                if (toastMsg) toastMsg.textContent = `Alert #${alertId} deleted successfully and cleared from citizen feeds.`;

                const toastEl = document.getElementById('alertActionToast');
                if (toastEl) {
                    const toast = new bootstrap.Toast(toastEl, { delay: 5000 });
                    toast.show();
                }
            } else {
                alert('Revocation Error: ' + (json.message || 'Failed to remove alert.'));
                deleteBtn.disabled = false;
                deleteBtn.innerHTML = originalHtml;
            }
        } catch (err) {
            alert('Request Failed: ' + err.message);
            deleteBtn.disabled = false;
            deleteBtn.innerHTML = originalHtml;
        }
    });
});
</script>

<!-- Notification Toast Container -->
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1090;">
    <div id="alertActionToast" class="toast align-items-center text-bg-dark border-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body d-flex align-items-center gap-2">
                <i class="bi bi-check-circle-fill text-success fs-4"></i>
                <div>
                    <strong class="d-block text-white" id="toastAlertTitle">Alert Revoked</strong>
                    <span id="toastAlertMessage" class="small text-white-50"></span>
                </div>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
