<?php
/**
 * 403 Access Forbidden View
 * Flood Risk Prediction & Relief Camp Locator
 */

require_once dirname(__DIR__, 2) . '/config/app.php';
require_once dirname(__DIR__, 2) . '/core/Auth.php';
require_once dirname(__DIR__, 2) . '/core/Helper.php';

$pageTitle = "Access Forbidden (403)";
require_once dirname(__DIR__) . '/layouts/header.php';
?>

<div class="container py-5 text-center">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-sm border-0 py-5 px-4 rounded-4">
                <div class="text-danger mb-3">
                    <i class="bi bi-shield-slash-fill" style="font-size: 4rem;"></i>
                </div>
                <h2 class="fw-bold text-dark">403 - Access Forbidden</h2>
                <p class="text-muted mb-4">
                    You do not have the required administrative or coordinator credentials to view this protected resource.
                </p>
                <div class="d-flex justify-content-center gap-2">
                    <a href="<?= BASE_URL ?>/views/citizen/index.php" class="btn btn-outline-secondary">
                        <i class="bi bi-house-door me-1"></i> Public Dashboard
                    </a>
                    <a href="<?= BASE_URL ?>/views/auth/login.php" class="btn btn-primary">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Sign In with Another Account
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once dirname(__DIR__) . '/layouts/footer.php'; ?>
