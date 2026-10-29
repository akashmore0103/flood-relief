<?php
/**
 * Master Footer Layout
 * Flood Risk Prediction & Relief Camp Locator
 */

require_once dirname(__DIR__, 2) . '/config/maps_config.php';
?>
</main>

<footer class="bg-dark text-white pt-4 pb-3 mt-auto border-top border-secondary">
    <div class="container">
        <div class="row g-4 mb-3">
            <div class="col-md-4">
                <h6 class="text-info fw-bold mb-2"><i class="bi bi-shield-shaded me-2"></i><?= htmlspecialchars(APP_NAME) ?></h6>
                <p class="text-white-50 small mb-2">
                    A comprehensive web-based disaster decision-support platform providing area-level flood risk assessment, live relief camp discovery, emergency rescue ticket triage, and authorized alert broadcasts.
                </p>
                <div class="badge bg-secondary text-white-50 font-monospace">MCA Academic Capstone Project</div>
            </div>

            <div class="col-md-4">
                <h6 class="text-warning fw-bold mb-2"><i class="bi bi-telephone-fill me-2"></i>Emergency Helplines</h6>
                <ul class="list-unstyled small mb-0">
                    <li class="mb-1"><span class="text-white-50">National Emergency:</span> <strong class="text-white">112</strong></li>
                    <li class="mb-1"><span class="text-white-50">Disaster Management Control:</span> <strong class="text-white">1077 / 1070</strong></li>
                    <li class="mb-1"><span class="text-white-50">Emergency Ambulance:</span> <strong class="text-white">108</strong></li>
                    <li class="mb-1"><span class="text-white-50">Fire &amp; Rescue Services:</span> <strong class="text-white">101</strong></li>
                </ul>
            </div>

            <div class="col-md-4">
                <h6 class="text-light fw-bold mb-2"><i class="bi bi-exclamation-triangle me-2"></i>Decision Support Disclaimer</h6>
                <p class="text-white-50 small mb-2">
                    Flood risk categories and proximity results are generated via rule-based decision models using available hydrological data. Citizens must follow official instructions from local disaster management authorities during real-world crises.
                </p>
                <a href="<?= BASE_URL ?>/database/test_connection.php" class="text-info small text-decoration-none">
                    <i class="bi bi-wrench-adjustable-circle me-1"></i> System Diagnostics &amp; DB Check
                </a>
            </div>
        </div>

        <hr class="border-secondary my-3">

        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center text-white-50 small">
            <div>
                &copy; <?= date('Y') ?> Disaster Management Web Portal. All rights reserved.
            </div>
            <div class="mt-2 mt-sm-0">
                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                    <i class="bi bi-circle-fill me-1" style="font-size: 0.5rem;"></i> System Operational
                </span>
                <span class="ms-2 font-monospace"><?= date('d M Y, h:i A') ?></span>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap 5 Bundle JS with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Leaflet JS (Zero-failure map fallback) -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<!-- Global App Configuration for JS -->
<script>
    window.APP_CONFIG = {
        baseUrl: '<?= BASE_URL ?>',
        csrfToken: '<?= CSRF::getToken() ?>',
        mapProvider: '<?= MAP_PROVIDER ?>',
        defaultLat: <?= MAP_DEFAULT_LAT ?>,
        defaultLng: <?= MAP_DEFAULT_LNG ?>,
        defaultZoom: <?= MAP_DEFAULT_ZOOM ?>
    };
</script>

<!-- Multi-Language (i18n) Engine -->
<script src="<?= BASE_URL ?>/assets/js/i18n.js"></script>
</body>
</html>
