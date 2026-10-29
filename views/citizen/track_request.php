<?php
/**
 * Public Emergency Ticket Tracker
 * Satisfies PRD Section 6 (Request Help Form & Tracking) and FR-14, FR-16
 */

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once dirname(__DIR__, 2) . '/core/Database.php';
require_once dirname(__DIR__, 2) . '/core/Helper.php';
require_once dirname(__DIR__, 2) . '/services/HelpRequestService.php';

$refQuery = trim($_GET['ref'] ?? '');
$ticket = null;
$error = null;

if (!empty($refQuery)) {
    try {
        $ticket = HelpRequestService::getByReference($refQuery);
        if (!$ticket) {
            $error = "No active emergency ticket found matching reference code: " . Helper::e($refQuery);
        }
    } catch (Throwable $e) {
        $error = "Lookup error: " . $e->getMessage();
    }
}

$pageTitle = "Track Emergency Ticket";
require_once dirname(__DIR__) . '/layouts/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center mb-4">
        <div class="col-lg-8 text-center">
            <h2 class="fw-bold text-dark mb-1">
                <i class="bi bi-search text-primary me-2"></i>Track Emergency Request
            </h2>
            <p class="text-muted">
                Enter your unique emergency reference code to view live triage status, assigned rescue team details, and action logs.
            </p>

            <!-- Search Form Card -->
            <div class="card shadow-sm border-0 p-3 rounded-4 bg-white mt-3">
                <form method="GET" action="" class="row g-2 align-items-center">
                    <div class="col-md-9">
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-light"><i class="bi bi-ticket-detailed"></i></span>
                            <input type="text" class="form-control font-monospace fw-bold" name="ref" 
                                   placeholder="e.g., REQ-20260905-XXXX" required 
                                   value="<?= Helper::e($refQuery) ?>">
                        </div>
                    </div>
                    <div class="col-md-3 d-grid">
                        <button type="submit" class="btn btn-primary btn-lg fw-semibold">
                            <i class="bi bi-arrow-right-circle me-1"></i> Track Now
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="alert alert-warning shadow-sm border-0 rounded-3 text-center py-4" role="alert">
                    <i class="bi bi-exclamation-triangle-fill fs-3 text-warning d-block mb-2"></i>
                    <h6 class="fw-bold"><?= Helper::e($error) ?></h6>
                    <p class="small text-muted mb-0">
                        Please check the spelling of your reference code or contact your district emergency control room.
                    </p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($ticket): ?>
        <div class="row g-4 justify-content-center">
            <!-- Ticket Summary Card -->
            <div class="col-lg-8">
                <div class="card shadow-sm border-0 rounded-4 overflow-hidden mb-4">
                    <div class="card-header bg-dark text-white p-4">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <span class="small text-white-50 text-uppercase fw-bold letter-spacing">Emergency Ticket</span>
                                <h3 class="fw-bold font-monospace mb-0 text-info"><?= Helper::e($ticket['reference_code']) ?></h3>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-<?= Helper::getStatusBadgeClass($ticket['status']) ?> fs-6 px-3 py-2">
                                    Status: <?= Helper::e($ticket['status']) ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="card-body p-4">
                        <div class="row g-3 mb-4">
                            <div class="col-sm-4">
                                <div class="p-3 bg-light rounded-3">
                                    <span class="small text-muted d-block">Urgency Level:</span>
                                    <span class="badge bg-<?= Helper::getUrgencyBadgeClass($ticket['urgency']) ?> px-2 py-1 mt-1">
                                        <?= Helper::e($ticket['urgency']) ?> Urgency
                                    </span>
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div class="p-3 bg-light rounded-3">
                                    <span class="small text-muted d-block">Request Type:</span>
                                    <strong class="text-dark d-block mt-1"><?= Helper::e($ticket['request_type']) ?></strong>
                                </div>
                            </div>
                            <div class="col-sm-4">
                                <div class="p-3 bg-light rounded-3">
                                    <span class="small text-muted d-block">Persons Affected:</span>
                                    <strong class="text-dark d-block mt-1"><?= (int)$ticket['people_count'] ?> person(s)</strong>
                                </div>
                            </div>
                        </div>

                        <h6 class="fw-bold text-dark small text-uppercase mb-2">Emergency Description &amp; Address</h6>
                        <div class="p-3 bg-light rounded-3 mb-3 border">
                            <p class="mb-2 text-dark"><?= nl2br(Helper::e($ticket['description'])) ?></p>
                            <div class="small text-muted">
                                <i class="bi bi-geo-alt-fill text-danger me-1"></i><strong>Location:</strong> <?= Helper::e($ticket['address']) ?> 
                                (<?= (float)$ticket['latitude'] ?>, <?= (float)$ticket['longitude'] ?>)
                            </div>
                        </div>

                        <?php if (!empty($ticket['assigned_coordinator_name'])): ?>
                            <div class="alert alert-info border-info d-flex align-items-center mb-4">
                                <i class="bi bi-person-check-fill fs-3 text-info me-3"></i>
                                <div>
                                    <span class="small text-muted d-block">Assigned Relief Responder:</span>
                                    <strong class="text-dark"><?= Helper::e($ticket['assigned_coordinator_name']) ?></strong>
                                    <?php if (!empty($ticket['assigned_coordinator_phone'])): ?>
                                        <span class="ms-2">(<a href="tel:<?= Helper::e($ticket['assigned_coordinator_phone']) ?>" class="text-info"><?= Helper::e($ticket['assigned_coordinator_phone']) ?></a>)</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Lifecycle Progress Timeline -->
                        <h6 class="fw-bold text-dark small text-uppercase mb-3">
                            <i class="bi bi-clock-history me-1 text-primary"></i> Response Lifecycle Timeline
                        </h6>
                        <div class="timeline ps-2 border-start border-2 border-primary ms-2">
                            <?php if (empty($ticket['logs'])): ?>
                                <p class="small text-muted">Awaiting coordinator review.</p>
                            <?php else: ?>
                                <?php foreach ($ticket['logs'] as $log): ?>
                                    <div class="mb-3 position-relative ps-3">
                                        <div class="position-absolute bg-primary rounded-circle" style="width: 10px; height: 10px; left: -6px; top: 6px;"></div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <strong class="text-dark small"><?= Helper::e($log['action']) ?></strong>
                                            <span class="small text-muted font-monospace"><?= Helper::formatDateTime($log['created_at']) ?></span>
                                        </div>
                                        <p class="small text-muted mb-0"><?= Helper::e($log['notes']) ?></p>
                                        <?php if (!empty($log['responder_name'])): ?>
                                            <small class="text-primary">Updated by <?= Helper::e($log['responder_name']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card-footer bg-light p-3 text-center">
                        <span class="small text-muted">
                            Ticket registered on <?= Helper::formatDateTime($ticket['created_at']) ?> (<?= Helper::timeAgo($ticket['created_at']) ?>).
                        </span>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
