<?php
/**
 * Administrator Relief Camp Master Management View
 * Satisfies PRD Section 5.3 (FR-09) & Section 6
 */

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once dirname(__DIR__, 2) . '/core/Database.php';
require_once dirname(__DIR__, 2) . '/core/Auth.php';
require_once dirname(__DIR__, 2) . '/core/CSRF.php';
require_once dirname(__DIR__, 2) . '/core/Helper.php';

Auth::requireRole(['Administrator']);

$db = Database::getInstance();

// Handle Direct Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CSRF::verifyOrDie();
    $campId = !empty($_POST['id']) ? (int)$_POST['id'] : null;

    $campData = [
        'zone_id' => !empty($_POST['zone_id']) ? (int)$_POST['zone_id'] : null,
        'name' => trim($_POST['name']),
        'address' => trim($_POST['address']),
        'district' => trim($_POST['district']),
        'latitude' => (float)$_POST['latitude'],
        'longitude' => (float)$_POST['longitude'],
        'contact_person' => trim($_POST['contact_person']),
        'contact_phone' => trim($_POST['contact_phone']),
        'total_capacity' => (int)$_POST['total_capacity'],
        'current_occupancy' => (int)($_POST['current_occupancy'] ?? 0),
        'status' => $_POST['status'] ?? 'Operational',
        'active' => isset($_POST['active']) ? true : false
    ];

    if ($campId) {
        $db->update('camps', $campData, 'id = :id', [':id' => $campId]);
        Helper::setFlash('success', "Relief camp '{$campData['name']}' updated.");
    } else {
        $campId = $db->insert('camps', $campData);
        Helper::setFlash('success', "New relief camp '{$campData['name']}' created.");
    }

    $db->insert('audit_logs', [
        'user_id' => Auth::id(),
        'action_type' => 'CAMP_MASTER_SAVE',
        'table_affected' => 'camps',
        'record_id' => $campId,
        'details' => "Camp '{$campData['name']}' saved in {$campData['district']}",
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
    ]);

    Helper::redirect('views/admin/camps.php');
}

$camps = $db->fetchAll("
    SELECT c.*, z.name AS zone_name 
    FROM `camps` c
    LEFT JOIN `zones` z ON c.zone_id = z.id
    ORDER BY c.district ASC, c.name ASC
");

$zones = $db->fetchAll("SELECT `id`, `name`, `district` FROM `zones` WHERE `status` = 'active' ORDER BY `name` ASC");

$pageTitle = "Relief Camps Directory Management";
require_once dirname(__DIR__) . '/layouts/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="bi bi-hospital-fill text-success me-2"></i>Relief Camp Master Directory
            </h2>
            <p class="text-muted small mb-0">Maintain designated flood shelters, capacities, and GPS navigation pins.</p>
        </div>
        <button class="btn btn-primary shadow-sm" onclick="openCampModal()">
            <i class="bi bi-plus-circle me-1"></i> Register New Shelter
        </button>
    </div>

    <!-- Camps Table Card -->
    <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark small">
                    <tr>
                        <th>Shelter Name &amp; Zone</th>
                        <th>District &amp; Coordinates</th>
                        <th>Beds (Occupied / Total)</th>
                        <th>Contact In-Charge</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody class="small">
                    <?php foreach ($camps as $camp): 
                        $cap = (int)$camp['total_capacity'];
                        $occ = (int)$camp['current_occupancy'];
                        $vac = max(0, $cap - $occ);
                    ?>
                        <tr class="<?= !$camp['active'] ? 'table-secondary text-muted' : '' ?>">
                            <td>
                                <strong class="text-dark d-block"><?= Helper::e($camp['name']) ?></strong>
                                <small class="text-muted">Zone: <?= Helper::e($camp['zone_name'] ?: 'Unassigned') ?></small>
                            </td>
                            <td>
                                <div><?= Helper::e($camp['district']) ?></div>
                                <small class="font-monospace text-muted"><?= (float)$camp['latitude'] ?>°, <?= (float)$camp['longitude'] ?>°</small>
                            </td>
                            <td>
                                <strong><?= $occ ?></strong> / <?= $cap ?>
                                <span class="badge bg-<?= $vac > 0 ? 'success' : 'danger' ?> ms-1"><?= $vac ?> free</span>
                            </td>
                            <td>
                                <div class="fw-semibold"><?= Helper::e($camp['contact_person']) ?></div>
                                <small class="text-muted"><a href="tel:<?= Helper::e($camp['contact_phone']) ?>"><?= Helper::e($camp['contact_phone']) ?></a></small>
                            </td>
                            <td>
                                <span class="badge bg-<?= match($camp['status']) {
                                    'Operational' => 'success',
                                    'Full' => 'danger',
                                    'Standby' => 'warning text-dark',
                                    default => 'secondary'
                                } ?>">
                                    <?= Helper::e($camp['status']) ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-sm btn-outline-secondary" onclick='openCampModal(<?= json_encode($camp) ?>)'>
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

<!-- Camp Modal -->
<div class="modal fade" id="campModal" tabindex="-1" aria-labelledby="campModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-dark text-white py-3">
                <h5 class="modal-title fw-bold" id="campModalLabel">Relief Shelter</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="" id="campForm">
                <?= CSRF::inputField() ?>
                <input type="hidden" id="camp_id" name="id" value="">

                <div class="modal-body p-4">
                    <div class="row g-3 mb-3">
                        <div class="col-md-7">
                            <label for="camp_name" class="form-label fw-semibold small">Shelter Name *</label>
                            <input type="text" class="form-control" id="camp_name" name="name" required placeholder="E.g., Govt Higher Secondary School Camp">
                        </div>
                        <div class="col-md-5">
                            <label for="camp_zone_id" class="form-label fw-semibold small">Associated Flood Zone</label>
                            <select class="form-select" id="camp_zone_id" name="zone_id">
                                <option value="">-- None / Standalone --</option>
                                <?php foreach ($zones as $z): ?>
                                    <option value="<?= $z['id'] ?>"><?= Helper::e($z['name']) ?> (<?= Helper::e($z['district']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label for="camp_address" class="form-label fw-semibold small">Full Address &amp; Landmarks *</label>
                            <input type="text" class="form-control" id="camp_address" name="address" required placeholder="Street address or location">
                        </div>
                        <div class="col-md-4">
                            <label for="camp_district" class="form-label fw-semibold small">District *</label>
                            <input type="text" class="form-control" id="camp_district" name="district" required placeholder="E.g., Ernakulam">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="camp_lat" class="form-label fw-semibold small">Latitude Coordinate *</label>
                            <input type="number" step="any" class="form-control font-monospace" id="camp_lat" name="latitude" required placeholder="10.1115">
                        </div>
                        <div class="col-md-6">
                            <label for="camp_lng" class="form-label fw-semibold small">Longitude Coordinate *</label>
                            <input type="number" step="any" class="form-control font-monospace" id="camp_lng" name="longitude" required placeholder="76.3550">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="camp_contact_person" class="form-label fw-semibold small">Officer In-Charge Name *</label>
                            <input type="text" class="form-control" id="camp_contact_person" name="contact_person" required placeholder="Name of Tahsildar / Camp Officer">
                        </div>
                        <div class="col-md-6">
                            <label for="camp_contact_phone" class="form-label fw-semibold small">Official Contact Phone *</label>
                            <input type="text" class="form-control" id="camp_contact_phone" name="contact_phone" required placeholder="+91 94470 12345">
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label for="camp_total_cap" class="form-label fw-semibold small">Total Bed Capacity *</label>
                            <input type="number" min="1" class="form-control font-monospace" id="camp_total_cap" name="total_capacity" required value="200">
                        </div>
                        <div class="col-md-4">
                            <label for="camp_current_occ" class="form-label fw-semibold small">Current Occupancy</label>
                            <input type="number" min="0" class="form-control font-monospace" id="camp_current_occ" name="current_occupancy" value="0">
                        </div>
                        <div class="col-md-4">
                            <label for="camp_status_select" class="form-label fw-semibold small">Operating State</label>
                            <select class="form-select" id="camp_status_select" name="status">
                                <option value="Operational">Operational</option>
                                <option value="Full">Full</option>
                                <option value="Standby">Standby</option>
                                <option value="Closed">Closed</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" id="camp_active" name="active" value="1" checked>
                        <label class="form-check-label fw-semibold small" for="camp_active">Active (Visible on public citizen locator)</label>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm fw-semibold">
                        <i class="bi bi-check2-circle me-1"></i> Save Relief Shelter
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let campModalInstance = null;

function openCampModal(camp = null) {
    document.getElementById('camp_id').value = camp ? camp.id : '';
    document.getElementById('camp_name').value = camp ? camp.name : '';
    document.getElementById('camp_zone_id').value = camp ? (camp.zone_id || '') : '';
    document.getElementById('camp_address').value = camp ? camp.address : '';
    document.getElementById('camp_district').value = camp ? camp.district : '';
    document.getElementById('camp_lat').value = camp ? camp.latitude : '';
    document.getElementById('camp_lng').value = camp ? camp.longitude : '';
    document.getElementById('camp_contact_person').value = camp ? camp.contact_person : '';
    document.getElementById('camp_contact_phone').value = camp ? camp.contact_phone : '';
    document.getElementById('camp_total_cap').value = camp ? camp.total_capacity : '250';
    document.getElementById('camp_current_occ').value = camp ? camp.current_occupancy : '0';
    document.getElementById('camp_status_select').value = camp ? camp.status : 'Operational';
    document.getElementById('camp_active').checked = camp ? (camp.active == 1) : true;

    document.getElementById('campModalLabel').textContent = camp ? 'Edit Relief Shelter' : 'Register New Relief Shelter';
    campModalInstance = new bootstrap.Modal(document.getElementById('campModal'));
    campModalInstance.show();
}
</script>

<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
