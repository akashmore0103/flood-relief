<?php
/**
 * Coordinator Help Request Queue & Triage Interface
 * Satisfies PRD Section 6 (Coordinator Panel) and FR-14 to FR-16
 */

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once dirname(__DIR__, 2) . '/core/Database.php';
require_once dirname(__DIR__, 2) . '/core/Auth.php';
require_once dirname(__DIR__, 2) . '/core/CSRF.php';
require_once dirname(__DIR__, 2) . '/core/Helper.php';

Auth::requireRole(['Relief Coordinator', 'Administrator']);

$db = Database::getInstance();

$filterStatus = $_GET['status'] ?? 'All';
$filterUrgency = $_GET['urgency'] ?? 'All';
$searchTerm = trim($_GET['search'] ?? '');

$sql = "SELECT hr.*, u.full_name AS assigned_coordinator_name 
        FROM `help_requests` hr
        LEFT JOIN `users` u ON hr.assigned_to = u.id
        WHERE 1=1";
$params = [];

if ($filterStatus !== 'All' && !empty($filterStatus)) {
    $sql .= " AND hr.status = :status";
    $params[':status'] = $filterStatus;
}

if ($filterUrgency !== 'All' && !empty($filterUrgency)) {
    $sql .= " AND hr.urgency = :urgency";
    $params[':urgency'] = $filterUrgency;
}

if (!empty($searchTerm)) {
    $sql .= " AND (hr.reference_code LIKE :search OR hr.guest_name LIKE :search OR hr.address LIKE :search OR hr.guest_phone LIKE :search)";
    $params[':search'] = "%{$searchTerm}%";
}

$sql .= " ORDER BY CASE hr.urgency WHEN 'Critical' THEN 1 WHEN 'High' THEN 2 WHEN 'Medium' THEN 3 WHEN 'Low' THEN 4 ELSE 5 END, hr.created_at DESC";
$requests = $db->fetchAll($sql, $params);

// Fetch Coordinators for assignment dropdown
$coordinators = $db->fetchAll("
    SELECT id, full_name, email, role_id 
    FROM `users` 
    WHERE `role_id` IN (1, 2) AND `status` = 'active' 
    ORDER BY `full_name` ASC
");

$pageTitle = "Emergency Request Triage Queue";
require_once dirname(__DIR__) . '/layouts/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="bi bi-inbox-fill text-primary me-2"></i>Emergency Request Triage Queue
            </h2>
            <p class="text-muted small mb-0">Review incoming assistance requests, assign response teams, and update lifecycle progress.</p>
        </div>
        <div>
            <span class="badge bg-primary fs-6 px-3 py-2"><?= count($requests) ?> Tickets Found</span>
        </div>
    </div>

    <!-- Filters & Search Bar -->
    <div class="card shadow-sm border-0 mb-4 rounded-3">
        <div class="card-body p-3">
            <form method="GET" action="" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-muted mb-1">Status Filter:</label>
                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="All" <?= $filterStatus === 'All' ? 'selected' : '' ?>>All Statuses</option>
                        <option value="New" <?= $filterStatus === 'New' ? 'selected' : '' ?>>New</option>
                        <option value="Acknowledged" <?= $filterStatus === 'Acknowledged' ? 'selected' : '' ?>>Acknowledged</option>
                        <option value="In Progress" <?= $filterStatus === 'In Progress' ? 'selected' : '' ?>>In Progress</option>
                        <option value="Resolved" <?= $filterStatus === 'Resolved' ? 'selected' : '' ?>>Resolved</option>
                        <option value="Closed" <?= $filterStatus === 'Closed' ? 'selected' : '' ?>>Closed</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-semibold text-muted mb-1">Urgency Filter:</label>
                    <select name="urgency" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="All" <?= $filterUrgency === 'All' ? 'selected' : '' ?>>All Urgencies</option>
                        <option value="Critical" <?= $filterUrgency === 'Critical' ? 'selected' : '' ?>>Critical</option>
                        <option value="High" <?= $filterUrgency === 'High' ? 'selected' : '' ?>>High</option>
                        <option value="Medium" <?= $filterUrgency === 'Medium' ? 'selected' : '' ?>>Medium</option>
                        <option value="Low" <?= $filterUrgency === 'Low' ? 'selected' : '' ?>>Low</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-semibold text-muted mb-1">Keyword Search:</label>
                    <input type="text" name="search" class="form-control form-control-sm" 
                           placeholder="Search ref code, name, phone, or address..." 
                           value="<?= Helper::e($searchTerm) ?>">
                </div>
                <div class="col-md-2 d-grid pt-md-3">
                    <button type="submit" class="btn btn-dark btn-sm">
                        <i class="bi bi-search me-1"></i> Search
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Requests Table Card -->
    <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark small">
                    <tr>
                        <th>Ref Code &amp; Urgency</th>
                        <th>Citizen &amp; Contact</th>
                        <th>Type &amp; Needs</th>
                        <th>Location &amp; Coordinates</th>
                        <th>Status</th>
                        <th>Assigned To</th>
                        <th class="text-end">Triage Action</th>
                    </tr>
                </thead>
                <tbody class="small">
                    <?php if (empty($requests)): ?>
                        <tr><td colspan="7" class="text-center py-5 text-muted">No assistance requests matching filter criteria.</td></tr>
                    <?php else: ?>
                        <?php foreach ($requests as $req): ?>
                            <tr id="row-req-<?= $req['id'] ?>" class="<?= $req['urgency'] === 'Critical' ? 'table-danger' : '' ?>">
                                <td>
                                    <span class="font-monospace fw-bold text-dark d-block"><?= Helper::e($req['reference_code']) ?></span>
                                    <span class="badge bg-<?= Helper::getUrgencyBadgeClass($req['urgency']) ?>">
                                        <?= Helper::e($req['urgency']) ?>
                                    </span>
                                    <small class="text-muted d-block"><?= Helper::timeAgo($req['created_at']) ?></small>
                                </td>
                                <td>
                                    <strong class="text-dark d-block"><?= Helper::e($req['guest_name']) ?></strong>
                                    <div class="mt-1">
                                        <a href="tel:<?= Helper::e($req['guest_phone']) ?>" class="btn btn-sm btn-success rounded-pill px-2 py-0 fw-bold d-inline-flex align-items-center shadow-sm">
                                            <i class="bi bi-telephone-outbound-fill me-1"></i> Call Citizen 📞
                                        </a>
                                    </div>
                                    <div class="small font-monospace text-muted mt-1"><?= Helper::e($req['guest_phone']) ?></div>
                                    <div class="mt-1">
                                        <span class="badge bg-success-subtle text-success border border-success-subtle" style="font-size: 0.65rem;">
                                            <i class="bi bi-check-circle-fill me-1"></i> 10-Digit Verified
                                        </span>
                                        <span class="text-muted small ms-1"><?= (int)$req['people_count'] ?> person(s)</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-secondary mb-1"><?= Helper::e($req['request_type']) ?></span>
                                    <?php 
                                        $sev = $req['water_severity'] ?? 'Waist-Level';
                                        $sevClass = match($sev) {
                                            'Roof-Level' => 'badge bg-danger pulse-emergency',
                                            'Waist-Level' => 'badge bg-warning text-dark',
                                            default => 'badge bg-info text-dark'
                                        };
                                    ?>
                                    <div class="mb-1"><span class="<?= $sevClass ?>"><i class="bi bi-water me-1"></i><?= Helper::e($sev) ?></span></div>
                                    <p class="mb-0 text-muted" style="max-width: 220px; font-size: 0.82rem;"><?= Helper::e($req['description']) ?></p>
                                </td>
                                <td>
                                    <div class="text-truncate" style="max-width: 180px;" title="<?= Helper::e($req['address']) ?>">
                                        <?= Helper::e($req['address']) ?>
                                    </div>
                                    <a href="https://www.google.com/maps?q=<?= (float)$req['latitude'] ?>,<?= (float)$req['longitude'] ?>" 
                                       target="_blank" class="small text-info text-decoration-none">
                                        <i class="bi bi-geo-alt"></i> View GPS Map
                                    </a>
                                </td>
                                <td>
                                    <span id="badge-status-<?= $req['id'] ?>" class="badge bg-<?= Helper::getStatusBadgeClass($req['status']) ?>">
                                        <?= Helper::e($req['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span id="text-assigned-<?= $req['id'] ?>" class="text-muted small">
                                        <?= Helper::e($req['assigned_coordinator_name'] ?: 'Unassigned') ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-primary" onclick='openTriageModal(<?= json_encode($req) ?>)'>
                                        <i class="bi bi-pencil-square me-1"></i> Update
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

<!-- Triage Action Modal -->
<div class="modal fade" id="triageModal" tabindex="-1" aria-labelledby="triageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-dark text-white py-3">
                <h5 class="modal-title fw-bold" id="triageModalLabel">
                    <i class="bi bi-shield-check me-2 text-info"></i> Update Request Triage
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="triageForm">
                <?= CSRF::inputField() ?>
                <input type="hidden" id="modalRequestId" name="request_id" value="">

                <div class="modal-body p-4">
                    <div class="p-3 bg-light rounded-3 mb-3 border">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="font-monospace fw-bold text-primary" id="modalRefCode">REQ-XXXX</span>
                            <span class="badge" id="modalUrgencyBadge">Critical</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <div>
                                <strong class="text-dark d-block" id="modalCitizenName">Citizen Name</strong>
                                <div class="small text-muted" id="modalAddress">Address</div>
                            </div>
                            <a href="#" id="modalCallCitizenBtn" class="btn btn-sm btn-success rounded-pill px-3 fw-bold">
                                <i class="bi bi-telephone-outbound-fill me-1"></i> Call 📞
                            </a>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="modalStatusSelect" class="form-label fw-semibold small">Lifecycle Status *</label>
                        <select class="form-select" id="modalStatusSelect" name="status" required>
                            <option value="New">New (Pending Review)</option>
                            <option value="Acknowledged">Acknowledged (Disaster Desk Aware)</option>
                            <option value="In Progress">In Progress (Rescue Team En Route)</option>
                            <option value="Resolved">Resolved (Safe / Rescued)</option>
                            <option value="Closed">Closed (Archived)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="modalAssignSelect" class="form-label fw-semibold small">Assign Responder / Coordinator</label>
                        <select class="form-select" id="modalAssignSelect" name="assigned_to">
                            <option value="0">-- Unassigned --</option>
                            <?php foreach ($coordinators as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= Helper::e($c['full_name']) ?> (<?= Helper::e($c['email']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="modalNotes" class="form-label fw-semibold small">Action Remarks &amp; Log Notes</label>
                        <textarea class="form-control" id="modalNotes" name="notes" rows="3" 
                                  placeholder="E.g., Boat unit 2 dispatched from Aluva Jetty with 4 life jackets."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="btnSaveTriage" class="btn btn-primary btn-sm fw-semibold">
                        <i class="bi bi-check2-circle me-1"></i> Save &amp; Log Action
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let activeModalInstance = null;

function openTriageModal(req) {
    document.getElementById('modalRequestId').value = req.id;
    document.getElementById('modalRefCode').textContent = req.reference_code;
    document.getElementById('modalCitizenName').textContent = `${req.guest_name} (${req.guest_phone})`;
    document.getElementById('modalAddress').textContent = `${req.address} - ${req.people_count} person(s)`;
    document.getElementById('modalCallCitizenBtn').href = 'tel:' + encodeURIComponent(req.guest_phone);

    const urgencyBadge = document.getElementById('modalUrgencyBadge');
    urgencyBadge.textContent = req.urgency;
    urgencyBadge.className = `badge bg-${req.urgency === 'Critical' ? 'danger' : 'warning text-dark'}`;

    document.getElementById('modalStatusSelect').value = req.status;
    document.getElementById('modalAssignSelect').value = req.assigned_to || 0;
    document.getElementById('modalNotes').value = '';

    activeModalInstance = new bootstrap.Modal(document.getElementById('triageModal'));
    activeModalInstance.show();
}

document.getElementById('triageForm')?.addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('btnSaveTriage');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';

    const formData = new FormData(e.target);
    const payload = Object.fromEntries(formData.entries());

    try {
        const res = await fetch(`${window.APP_CONFIG.baseUrl}/api/update_request.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const json = await res.json();
        if (!json.success) {
            throw new Error(json.message || 'Update failed.');
        }

        // Update row visually
        const reqId = payload.request_id;
        const statusBadge = document.getElementById(`badge-status-${reqId}`);
        if (statusBadge) {
            statusBadge.textContent = payload.status;
            statusBadge.className = `badge bg-${getStatusClass(payload.status)}`;
        }

        const assignSelect = document.getElementById('modalAssignSelect');
        const selectedCoordText = assignSelect.options[assignSelect.selectedIndex].text;
        const assignTextEl = document.getElementById(`text-assigned-${reqId}`);
        if (assignTextEl) {
            assignTextEl.textContent = payload.assigned_to > 0 ? selectedCoordText.split('(')[0].trim() : 'Unassigned';
        }

        if (activeModalInstance) activeModalInstance.hide();
        alert('Ticket status updated and audit record logged.');

    } catch (err) {
        alert('Triage Error: ' + err.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Save &amp; Log Action';
    }
});

function getStatusClass(status) {
    switch (status.toUpperCase()) {
        case 'NEW': return 'primary';
        case 'ACKNOWLEDGED': return 'info text-dark';
        case 'IN PROGRESS': return 'warning text-dark';
        case 'RESOLVED': return 'success';
        case 'CLOSED': return 'secondary';
        default: return 'light text-dark';
    }
}
</script>

<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
