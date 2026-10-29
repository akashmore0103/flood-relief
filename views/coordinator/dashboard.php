<?php
/**
 * Relief Coordinator Operational Dashboard
 * Satisfies PRD Section 6 (Coordinator Panel)
 */

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once dirname(__DIR__, 2) . '/core/Database.php';
require_once dirname(__DIR__, 2) . '/core/Auth.php';
require_once dirname(__DIR__, 2) . '/core/Helper.php';

// Enforce Coordinator or Admin role
Auth::requireRole(['Relief Coordinator', 'Administrator']);

$db = Database::getInstance();
$userId = Auth::id();

// Fetch Operational Metrics
$stats = [
    'new_requests' => (int)$db->fetchValue("SELECT COUNT(*) FROM `help_requests` WHERE `status` = 'New'"),
    'critical_requests' => (int)$db->fetchValue("SELECT COUNT(*) FROM `help_requests` WHERE `urgency` = 'Critical' AND `status` != 'Closed'"),
    'in_progress' => (int)$db->fetchValue("SELECT COUNT(*) FROM `help_requests` WHERE `status` = 'In Progress'"),
    'operational_camps' => (int)$db->fetchValue("SELECT COUNT(*) FROM `camps` WHERE `active` = TRUE AND `status` = 'Operational'")
];

// Recent Pending Requests
$pendingRequests = $db->fetchAll("
    SELECT hr.*, u.full_name AS assigned_coordinator_name 
    FROM `help_requests` hr
    LEFT JOIN `users` u ON hr.assigned_to = u.id
    WHERE hr.status IN ('New', 'Acknowledged', 'In Progress')
    ORDER BY CASE hr.urgency WHEN 'Critical' THEN 1 WHEN 'High' THEN 2 WHEN 'Medium' THEN 3 WHEN 'Low' THEN 4 ELSE 5 END, hr.created_at DESC
    LIMIT 6
");

// Relief Camps Overview
$camps = $db->fetchAll("
    SELECT c.*, (c.total_capacity - c.current_occupancy) AS available_beds 
    FROM `camps` c 
    WHERE c.active = TRUE 
    ORDER BY c.status ASC, c.name ASC 
    LIMIT 6
");

$pageTitle = "Coordinator Operations Hub";
require_once dirname(__DIR__) . '/layouts/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="bi bi-kanban-fill text-info me-2"></i>Coordinator Operations Hub
            </h2>
            <p class="text-muted small mb-0">
                Welcome, <?= Helper::e(Auth::user()['full_name']) ?> &bull; Emergency Response Triage &amp; Shelter Management
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= BASE_URL ?>/views/coordinator/requests.php" class="btn btn-primary shadow-sm">
                <i class="bi bi-list-task me-1"></i> Full Request Queue (<?= $stats['new_requests'] ?> New)
            </a>
            <a href="<?= BASE_URL ?>/views/coordinator/camp_status.php" class="btn btn-outline-dark">
                <i class="bi bi-hospital me-1"></i> Update Camp Capacity
            </a>
        </div>
    </div>

    <!-- Status Cards Grid -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-3">
            <div class="card stat-card p-3 border-0 bg-white">
                <div class="d-flex align-items-center">
                    <div class="p-3 bg-danger bg-opacity-10 text-danger rounded-3 me-3 pulse-emergency">
                        <i class="bi bi-exclamation-octagon-fill fs-3"></i>
                    </div>
                    <div>
                        <div class="small text-muted fw-semibold">Critical Urgency Tickets</div>
                        <h3 class="fw-bold mb-0 text-danger"><?= $stats['critical_requests'] ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card stat-card p-3 border-0 bg-white">
                <div class="d-flex align-items-center">
                    <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-3 me-3">
                        <i class="bi bi-bell-fill fs-3"></i>
                    </div>
                    <div>
                        <div class="small text-muted fw-semibold">Unassigned / New</div>
                        <h3 class="fw-bold mb-0 text-primary"><?= $stats['new_requests'] ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card stat-card p-3 border-0 bg-white">
                <div class="d-flex align-items-center">
                    <div class="p-3 bg-warning bg-opacity-10 text-warning rounded-3 me-3">
                        <i class="bi bi-arrow-repeat fs-3"></i>
                    </div>
                    <div>
                        <div class="small text-muted fw-semibold">In Progress Rescues</div>
                        <h3 class="fw-bold mb-0 text-dark"><?= $stats['in_progress'] ?></h3>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="card stat-card p-3 border-0 bg-white">
                <div class="d-flex align-items-center">
                    <div class="p-3 bg-success bg-opacity-10 text-success rounded-3 me-3">
                        <i class="bi bi-house-check-fill fs-3"></i>
                    </div>
                    <div>
                        <div class="small text-muted fw-semibold">Operational Shelters</div>
                        <h3 class="fw-bold mb-0 text-success"><?= $stats['operational_camps'] ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Tickets Queue & Camp Capacity Overview -->
    <div class="row g-4">
        <!-- Urgent Help Requests Table -->
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 rounded-4 h-100">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold text-dark mb-0"><i class="bi bi-shield-exclamation text-danger me-2"></i>Priority Help Request Queue</h5>
                    <a href="<?= BASE_URL ?>/views/coordinator/requests.php" class="small text-primary text-decoration-none">View All &rarr;</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th>Ref &amp; Urgency</th>
                                <th>Contact &amp; Persons</th>
                                <th>Assistance Type</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pendingRequests)): ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">No pending emergency requests.</td></tr>
                            <?php else: ?>
                                <?php foreach ($pendingRequests as $req): ?>
                                    <tr>
                                        <td>
                                            <span class="font-monospace fw-bold text-dark small d-block"><?= Helper::e($req['reference_code']) ?></span>
                                            <span class="badge bg-<?= Helper::getUrgencyBadgeClass($req['urgency']) ?>">
                                                <?= Helper::e($req['urgency']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong class="text-dark small d-block"><?= Helper::e($req['guest_name']) ?></strong>
                                            <small class="text-muted"><a href="tel:<?= Helper::e($req['guest_phone']) ?>"><?= Helper::e($req['guest_phone']) ?></a> &bull; <?= (int)$req['people_count'] ?> person(s)</small>
                                        </td>
                                        <td>
                                            <span class="small fw-semibold"><?= Helper::e($req['request_type']) ?></span>
                                            <small class="text-muted d-block text-truncate" style="max-width: 180px;"><?= Helper::e($req['description']) ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= Helper::getStatusBadgeClass($req['status']) ?>">
                                                <?= Helper::e($req['status']) ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <a href="<?= BASE_URL ?>/views/coordinator/requests.php?focus=<?= $req['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-pencil-square"></i> Triage
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Live Camp Bed Capacity Column -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 rounded-4 h-100">
                <div class="card-header bg-white py-3 border-0 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold text-dark mb-0"><i class="bi bi-hospital text-success me-2"></i>Shelter Occupancies</h5>
                    <a href="<?= BASE_URL ?>/views/coordinator/camp_status.php" class="small text-primary text-decoration-none">Manage &rarr;</a>
                </div>
                <div class="card-body p-3">
                    <?php foreach ($camps as $camp): 
                        $cap = (int)$camp['total_capacity'];
                        $occ = (int)$camp['current_occupancy'];
                        $pct = $cap > 0 ? round(($occ / $cap) * 100) : 100;
                        $color = ($camp['status'] === 'Full' || $pct >= 90) ? 'danger' : ($pct >= 70 ? 'warning' : 'success');
                    ?>
                        <div class="p-2 mb-2 bg-light rounded-3 border">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <strong class="small text-dark text-truncate" style="max-width: 190px;"><?= Helper::e($camp['name']) ?></strong>
                                <span class="badge bg-<?= $color ?>"><?= Helper::e($camp['status']) ?></span>
                            </div>
                            <div class="d-flex justify-content-between small text-muted mb-1">
                                <span><?= $occ ?> / <?= $cap ?> occupied</span>
                                <span class="fw-bold text-dark"><?= $pct ?>%</span>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-<?= $color ?>" role="progressbar" style="width: <?= $pct ?>%;"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
