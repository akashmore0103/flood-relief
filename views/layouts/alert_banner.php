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
                    FROM `alerts` a 
                    LEFT JOIN `zones` z ON a.target_zone_id = z.id 
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
<div class="alert-ticker py-2 px-3 shadow-sm border-bottom border-danger">
    <div class="container d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center">
            <span class="badge bg-light text-danger fw-bold me-2 px-2 py-1 pulse-emergency">
                <i class="bi bi-exclamation-octagon-fill me-1"></i> EMERGENCY ALERT
            </span>
            <?php foreach ($criticalAlerts as $alert): ?>
                <span class="text-white me-3 small">
                    <strong><?= Helper::e($alert['title']) ?></strong>: 
                    <?= Helper::e(substr($alert['message'], 0, 110)) ?>...
                    <?php if (!empty($alert['zone_name'])): ?>
                        <span class="badge bg-dark text-light ms-1"><?= Helper::e($alert['zone_name']) ?></span>
                    <?php endif; ?>
                </span>
            <?php endforeach; ?>
        </div>
        <div>
            <a href="<?= BASE_URL ?>/views/citizen/alerts.php" class="btn btn-light btn-sm text-danger fw-bold py-0 px-2" style="font-size: 0.8rem;">
                View All Alerts <i class="bi bi-arrow-right"></i>
            </a>
        </div>
    </div>
</div>
<?php endif; ?>
