<?php
/**
 * Public Missing & Found Persons Registry
 * Community tracing and family reunification board
 * Flood Risk Prediction & Relief Camp Locator
 */

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once dirname(__DIR__, 2) . '/core/Database.php';
require_once dirname(__DIR__, 2) . '/core/Auth.php';
require_once dirname(__DIR__, 2) . '/core/Helper.php';
require_once dirname(__DIR__, 2) . '/core/I18n.php';
require_once dirname(__DIR__, 2) . '/services/MissingPersonService.php';

$db = Database::getInstance();

$filters = [
    'query' => trim($_GET['query'] ?? ''),
    'status' => $_GET['status'] ?? '',
    'gender' => $_GET['gender'] ?? '',
    'camp_id' => !empty($_GET['camp_id']) ? (int)$_GET['camp_id'] : null
];

$persons = MissingPersonService::search($filters);
$stats = MissingPersonService::getStats();

// Relief Camps for the Sheltered selector
$camps = $db->fetchAll("SELECT id, name, district FROM `camps` WHERE `status` != 'Inactive' ORDER BY `name` ASC");

$pageTitle = "Missing & Found Persons Registry";
require_once dirname(__DIR__) . '/layouts/header.php';
?>

<div class="container py-4">
    <!-- Header Hero Banner -->
    <div class="glass-panel p-4 mb-4">
        <div class="row align-items-center">
            <div class="col-lg-7">
                <span class="badge bg-primary px-3 py-1 mb-2 font-monospace" data-i18n="nav_missing">COMMUNITY REUNIFICATION</span>
                <h2 class="fw-bold text-dark mb-1">Missing &amp; Found Persons Registry</h2>
                <p class="text-muted mb-0 small">
                    A public tracing directory to search, report, and reunite individuals displaced, stranded, or sheltered during flood emergencies.
                </p>
            </div>
            <div class="col-lg-5 text-lg-end mt-3 mt-lg-0 d-flex justify-content-lg-end gap-2">
                <button class="btn btn-danger btn-sm rounded-pill px-3 shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#reportMissingModal">
                    <i class="bi bi-person-fill-exclamation me-1"></i> Report Missing Person
                </button>
                <button class="btn btn-outline-success btn-sm rounded-pill px-3 shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#reportFoundModal">
                    <i class="bi bi-person-check-fill me-1"></i> Report Found / Sheltered
                </button>
            </div>
        </div>
    </div>

    <!-- Live Statistics Counter Cards -->
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="card glass-card border-0 p-3 text-center shadow-sm">
                <div class="text-muted small fw-semibold text-uppercase">Total Reports</div>
                <div class="display-6 fw-bold text-dark font-monospace"><?= $stats['total'] ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card glass-card border-0 p-3 text-center shadow-sm border-start border-danger border-4">
                <div class="text-danger small fw-semibold text-uppercase">Currently Missing</div>
                <div class="display-6 fw-bold text-danger font-monospace"><?= $stats['missing'] ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card glass-card border-0 p-3 text-center shadow-sm border-start border-warning border-4">
                <div class="text-warning small fw-semibold text-uppercase">Found / Rescued</div>
                <div class="display-6 fw-bold text-warning font-monospace"><?= $stats['found'] ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card glass-card border-0 p-3 text-center shadow-sm border-start border-success border-4">
                <div class="text-success small fw-semibold text-uppercase">Sheltered / Reunited</div>
                <div class="display-6 fw-bold text-success font-monospace"><?= $stats['sheltered'] + $stats['reunited'] ?></div>
            </div>
        </div>
    </div>

    <!-- Search & Filter Controls -->
    <div class="card glass-card border-0 shadow-sm p-3 mb-4">
        <form method="GET" action="" class="row g-2 align-items-center">
            <div class="col-md-5">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                    <input type="text" name="query" class="form-control" 
                           placeholder="Search by name, last seen locality, or keyword..." 
                           value="<?= Helper::e($filters['query']) ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="" <?= empty($filters['status']) ? 'selected' : '' ?>>All Statuses</option>
                    <option value="Missing" <?= $filters['status'] === 'Missing' ? 'selected' : '' ?>>Missing</option>
                    <option value="Found" <?= $filters['status'] === 'Found' ? 'selected' : '' ?>>Found</option>
                    <option value="Sheltered" <?= $filters['status'] === 'Sheltered' ? 'selected' : '' ?>>Sheltered in Camp</option>
                    <option value="Reunited" <?= $filters['status'] === 'Reunited' ? 'selected' : '' ?>>Reunited</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="gender" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="" <?= empty($filters['gender']) ? 'selected' : '' ?>>Any Gender</option>
                    <option value="Male" <?= $filters['gender'] === 'Male' ? 'selected' : '' ?>>Male</option>
                    <option value="Female" <?= $filters['gender'] === 'Female' ? 'selected' : '' ?>>Female</option>
                    <option value="Other" <?= $filters['gender'] === 'Other' ? 'selected' : '' ?>>Other</option>
                </select>
            </div>
            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-dark btn-sm rounded-pill">
                    <i class="bi bi-funnel-fill me-1"></i> Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Persons Directory Cards Grid -->
    <div class="row g-4">
        <?php if (empty($persons)): ?>
            <div class="col-12 text-center py-5">
                <div class="glass-card p-5">
                    <i class="bi bi-people fs-1 text-muted d-block mb-3"></i>
                    <h5 class="fw-bold text-dark">No records found</h5>
                    <p class="text-muted small">No missing or rescued individuals match the search filters.</p>
                    <a href="<?= BASE_URL ?>/views/citizen/missing_persons.php" class="btn btn-sm btn-outline-primary rounded-pill">
                        Clear All Filters
                    </a>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($persons as $p): ?>
                <?php 
                    $badgeBg = match($p['status']) {
                        'Missing' => 'bg-danger pulse-emergency',
                        'Found' => 'bg-warning text-dark',
                        'Sheltered' => 'bg-info text-dark',
                        'Reunited' => 'bg-success',
                        default => 'bg-secondary'
                    };
                    $hasPhoto = !empty($p['photo_url']) && file_exists(dirname(__DIR__, 2) . '/' . $p['photo_url']);
                    $photoSrc = $hasPhoto ? BASE_URL . '/' . $p['photo_url'] : null;
                ?>
                <div class="col-xl-3 col-lg-4 col-md-6">
                    <div class="card glass-card h-100 border-0 shadow-sm overflow-hidden">
                        <!-- Photo Thumbnail or Avatar Banner -->
                        <div class="position-relative bg-light text-center py-4 border-bottom" style="min-height: 180px;">
                            <?php if ($photoSrc): ?>
                                <img src="<?= $photoSrc ?>" alt="<?= Helper::e($p['person_name']) ?>" 
                                     class="img-fluid rounded" style="max-height: 170px; object-fit: cover;">
                            <?php else: ?>
                                <i class="bi bi-person-bounding-box display-3 text-secondary opacity-50"></i>
                                <small class="text-muted d-block mt-2">No Photograph Provided</small>
                            <?php endif; ?>
                            <span class="position-absolute top-0 end-0 m-3 badge <?= $badgeBg ?> px-2 py-1">
                                <?= Helper::e($p['status']) ?>
                            </span>
                        </div>

                        <div class="card-body">
                            <h5 class="fw-bold text-dark mb-1"><?= Helper::e($p['person_name']) ?></h5>
                            <div class="text-muted small mb-2">
                                <span><?= $p['age'] ? (int)$p['age'] . ' yrs' : 'Age Unknown' ?></span> &bull; 
                                <span><?= Helper::e($p['gender']) ?></span>
                            </div>

                            <?php if (!empty($p['last_seen_location'])): ?>
                                <div class="small mb-1">
                                    <strong class="text-muted">Last Seen:</strong>
                                    <span class="text-dark"><?= Helper::e($p['last_seen_location']) ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($p['found_location'])): ?>
                                <div class="small mb-1">
                                    <strong class="text-muted">Found At:</strong>
                                    <span class="text-dark"><?= Helper::e($p['found_location']) ?></span>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($p['camp_name'])): ?>
                                <div class="small mb-2 p-2 bg-info-subtle border border-info-subtle rounded-3">
                                    <i class="bi bi-hospital text-info me-1"></i>
                                    <strong>Sheltered at:</strong> <?= Helper::e($p['camp_name']) ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($p['description'])): ?>
                                <p class="text-muted small mb-3 text-truncate" title="<?= Helper::e($p['description']) ?>">
                                    <?= Helper::e($p['description']) ?>
                                </p>
                            <?php endif; ?>

                            <!-- Contact Dial Button -->
                            <div class="d-grid mt-3">
                                <a href="tel:<?= Helper::e($p['contact_phone']) ?>" class="btn btn-outline-primary btn-sm rounded-pill fw-semibold">
                                    <i class="bi bi-telephone-fill me-1"></i> Contact: <?= Helper::e($p['contact_phone']) ?>
                                </a>
                            </div>
                        </div>

                        <div class="card-footer bg-transparent border-0 pt-0 pb-3 text-muted small text-center" style="font-size: 0.72rem;">
                            Reported <?= Helper::timeAgo($p['created_at']) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal 1: Report Missing Person -->
<div class="modal fade" id="reportMissingModal" tabindex="-1" aria-labelledby="reportMissingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-danger text-white py-3">
                <h5 class="modal-title fw-bold" id="reportMissingModalLabel">
                    <i class="bi bi-person-fill-exclamation me-2"></i> Report Missing Person
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="missingReportForm" enctype="multipart/form-data">
                <input type="hidden" name="status" value="Missing">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="m_person_name" class="form-label fw-semibold small">Full Name of Missing Person *</label>
                        <input type="text" class="form-control" id="m_person_name" name="person_name" required placeholder="e.g. Ramesh Deshmukh">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label for="m_age" class="form-label fw-semibold small">Age (Approx)</label>
                            <input type="number" class="form-control" id="m_age" name="age" min="1" max="120" placeholder="e.g. 42">
                        </div>
                        <div class="col-6">
                            <label for="m_gender" class="form-label fw-semibold small">Gender</label>
                            <select class="form-select" id="m_gender" name="gender">
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="m_last_seen" class="form-label fw-semibold small">Last Seen Location / Landmark</label>
                        <input type="text" class="form-control" id="m_last_seen" name="last_seen_location" placeholder="e.g. Near Sangli Bridge, Ward 3">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label for="m_contact_person" class="form-label fw-semibold small">Your Name (Family Contact)</label>
                            <input type="text" class="form-control" id="m_contact_person" name="contact_person" placeholder="e.g. Sunita Deshmukh">
                        </div>
                        <div class="col-6">
                            <label for="m_contact_phone" class="form-label fw-semibold small">Contact Phone Number *</label>
                            <input type="tel" class="form-control" id="m_contact_phone" name="contact_phone" required placeholder="10-digit mobile">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="m_photo" class="form-label fw-semibold small">Upload Photograph (Optional)</label>
                        <input type="file" class="form-control" id="m_photo" name="photo" accept="image/jpeg,image/png,image/webp">
                        <div class="form-text small">JPG, PNG or WebP under 5MB.</div>
                    </div>
                    <div class="mb-3">
                        <label for="m_description" class="form-label fw-semibold small">Physical Description &amp; Clothing</label>
                        <textarea class="form-control" id="m_description" name="description" rows="2" 
                                  placeholder="e.g. Wearing blue shirt, black pants, scar on left hand."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="btnSubmitMissing" class="btn btn-danger btn-sm fw-bold">
                        <i class="bi bi-send-fill me-1"></i> Submit Missing Report
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 2: Report Found / Sheltered Person -->
<div class="modal fade" id="reportFoundModal" tabindex="-1" aria-labelledby="reportFoundModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-success text-white py-3">
                <h5 class="modal-title fw-bold" id="reportFoundModalLabel">
                    <i class="bi bi-person-check-fill me-2"></i> Report Found / Sheltered Person
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="foundReportForm" enctype="multipart/form-data">
                <input type="hidden" name="status" value="Found">
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label for="f_person_name" class="form-label fw-semibold small">Person Name (or 'Unidentified') *</label>
                        <input type="text" class="form-control" id="f_person_name" name="person_name" required placeholder="e.g. Anand Shinde or Unidentified Elderly Male">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label for="f_age" class="form-label fw-semibold small">Estimated Age</label>
                            <input type="number" class="form-control" id="f_age" name="age" min="1" max="120" placeholder="e.g. 50">
                        </div>
                        <div class="col-6">
                            <label for="f_gender" class="form-label fw-semibold small">Gender</label>
                            <select class="form-select" id="f_gender" name="gender">
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="f_sheltered_camp" class="form-label fw-semibold small">Sheltered at Relief Camp (Optional)</label>
                        <select class="form-select" id="f_sheltered_camp" name="sheltered_at_camp_id">
                            <option value="">-- Outside Camp / Community Care --</option>
                            <?php foreach ($camps as $cp): ?>
                                <option value="<?= $cp['id'] ?>"><?= Helper::e($cp['name']) ?> (<?= Helper::e($cp['district']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="f_found_loc" class="form-label fw-semibold small">Location Where Found / Rescued</label>
                        <input type="text" class="form-control" id="f_found_loc" name="found_location" placeholder="e.g. Rescued near Nanded bus terminal">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label for="f_contact_person" class="form-label fw-semibold small">Caregiver / Shelter Incharge</label>
                            <input type="text" class="form-control" id="f_contact_person" name="contact_person" placeholder="e.g. Camp Officer Pawar">
                        </div>
                        <div class="col-6">
                            <label for="f_contact_phone" class="form-label fw-semibold small">Shelter / Contact Phone *</label>
                            <input type="tel" class="form-control" id="f_contact_phone" name="contact_phone" required placeholder="10-digit mobile">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="f_photo" class="form-label fw-semibold small">Upload Photo for Identification</label>
                        <input type="file" class="form-control" id="f_photo" name="photo" accept="image/jpeg,image/png,image/webp">
                    </div>
                    <div class="mb-3">
                        <label for="f_description" class="form-label fw-semibold small">Condition &amp; Description</label>
                        <textarea class="form-control" id="f_description" name="description" rows="2" 
                                  placeholder="e.g. In stable condition, has minor injury on knee."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="btnSubmitFound" class="btn btn-success btn-sm fw-bold">
                        <i class="bi bi-check-circle-fill me-1"></i> Register Rescued Person
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Missing form submission
    const handleReportSubmit = (formId, btnId) => {
        document.getElementById(formId)?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const form = e.target;
            const btn = document.getElementById(btnId);
            btn.disabled = true;

            const formData = new FormData(form);

            try {
                const res = await fetch(`${window.APP_CONFIG.baseUrl}/api/missing_persons.php`, {
                    method: 'POST',
                    body: formData
                });
                const json = await res.json();
                if (json.success) {
                    alert('Report successfully registered to community board.');
                    location.reload();
                } else {
                    alert('Submission error: ' + json.message);
                }
            } catch (err) {
                alert('Network error: ' + err.message);
            } finally {
                btn.disabled = false;
            }
        });
    };

    handleReportSubmit('missingReportForm', 'btnSubmitMissing');
    handleReportSubmit('foundReportForm', 'btnSubmitFound');
});
</script>

<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
