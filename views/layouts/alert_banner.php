<?php
/**
 * Global Emergency Alert Banner
 * Displays high-priority evacuation advisories across all pages
 */

$criticalAlerts = [];

try {
    if (class_exists('Database')) {
        $db = Database::getInstance();
        if ($db->isConnected()) {
            $sql = "SELECT a.*, z.name AS zone_name 
                    FROM alerts a 
                    LEFT JOIN zones z ON a.target_zone_id = z.id 
                    WHERE a.status = 'Published' 
                      AND (a.is_active = TRUE OR a.is_active IS NULL)
                      AND a.severity IN ('Critical Danger', 'Evacuation') 
                      AND (a.expires_at IS NULL OR a.expires_at > NOW())
                    ORDER BY a.created_at DESC 
                    LIMIT 2";
            $criticalAlerts = $db->fetchAll($sql);
        }
    }
} catch (Throwable $e) {
    // Fail gracefully in accordance with PRD Section 11
    $criticalAlerts = [];
}
?>

<?php if (!empty($criticalAlerts)): ?>
<div class="alert-ticker py-2 px-3 shadow-sm border-bottom border-danger" style="background-color: #dc2626 !important; color: #ffffff !important;">
    <div class="container d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center flex-wrap gap-2">
            <span class="badge bg-white text-danger fw-bold px-2 py-1 pulse-emergency" style="color: #dc2626 !important; font-weight: 800;">
                <i class="bi bi-exclamation-octagon-fill me-1"></i> EMERGENCY ALERT
            </span>
            <?php foreach ($criticalAlerts as $alert): ?>
                <span class="text-white small d-inline-flex align-items-center flex-wrap gap-1" style="color: #ffffff !important;">
                    <strong><?= Helper::e($alert['title']) ?>:</strong> 
                    <span><?= Helper::e(substr($alert['message'] ?? '', 0, 110)) ?>...</span>
                    <?php if (!empty($alert['zone_name'])): ?>
                        <span class="badge bg-dark text-light px-2 py-1"><?= Helper::e($alert['zone_name']) ?></span>
                    <?php endif; ?>
                </span>
            <?php endforeach; ?>
        </div>
        <div>
            <a href="<?= BASE_URL ?>/views/citizen/alerts.php" class="btn btn-light btn-sm text-danger fw-bold py-1 px-3 rounded-pill shadow-sm" style="color: #dc2626 !important; font-size: 0.8rem;">
                View All Alerts <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>
    </div>
</div>
<?php endif; ?>